<?php
declare(strict_types=1);

namespace App\Common\PublicAPI;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\PublicAPI\Queries;
use App\Common\Database\PublicAPI\QueriesPayload;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Validator;
use Comely\Buffer\Buffer;

/**
 * Class Query
 * @package App\Common\PublicAPI
 * @property bool $checksumVerified
 */
class Query extends AbstractAppModel
{
    public const TABLE = Queries::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $ipAddress;
    /** @var string */
    public string $method;
    /** @var string */
    public string $endpoint;
    /** @var float */
    public float $startOn;
    /** @var float */
    public float $endOn;
    /** @var null|int */
    public ?int $resCode = null;
    /** @var null|int */
    public ?int $resLen = null;
    /** @var null|int */
    public ?int $flagUserId = null;

    /** @var null|QueryPayload */
    private ?QueryPayload $_payload = null;


    /**
     * @return void
     */
    public function beforeQuery(): void
    {
        if (strlen($this->endpoint) > 512) {
            $this->endpoint = substr($this->endpoint, 0, 512);
        }
    }

    /**
     * @return void
     * @throws AppException
     * @throws \Comely\Security\Exception\CipherException
     */
    public function validateChecksum(): void
    {
        if ($this->checksum()->raw() !== $this->private("checksum")) {
            throw new AppException(sprintf('Invalid checksum of public API query ray # %d', $this->id));
        }

        $this->checksumVerified = true;
    }

    /**
     * @return Buffer
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Security\Exception\CipherException
     */
    public function checksum(): Buffer
    {
        $raw = sprintf(
            '%d:%s:%s:%s:%s:%s:%s:%s:%s:%d',
            $this->id,
            $this->ipAddress,
            strtolower(trim($this->method)),
            strtolower(trim($this->endpoint)),
            $this->startOn,
            $this->endOn,
            $this->resCode ?? 0,
            $this->resLen ?? 0,
            $this->private("flagApiSess") ?? "",
            $this->flagUserId ?? 0
        );

        return $this->aK->ciphers->secondary()->pbkdf2("sha1", $raw, 1000);
    }

    /**
     * @return QueryPayload
     * @throws AppException
     */
    public function payload(): QueryPayload
    {
        if ($this->_payload) {
            return $this->_payload;
        }

        try {

            $row = $this->aK->db->apiLogs()->query()->table(QueriesPayload::TABLE)
                ->where("`query`=?", [$this->id])
                ->fetch();
            if ($row->count() !== 1) {
                throw new AppModelNotFoundException('Public API query payload row not found');
            }

            $encrypted = $row->row()["encrypted"];
            if (!$encrypted || !is_string($encrypted)) {
                throw new AppException('Failed to retrieve encrypted payload blob');
            }

            $payload = $this->aK->ciphers->secondary()->decrypt(new Buffer($encrypted));
            if (!$payload instanceof QueryPayload) {
                throw new AppException('Failed to decrypt API query payload');
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve query payload');
        }

        $this->_payload = $payload;
        return $this->_payload;
    }

    /**
     * @return array
     * @throws AppException
     */
    public function array(): array
    {
        try {
            $filtered = Validator::JSON_Filter($this);
        } catch (\JsonException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            throw new AppException(sprintf('Could not convert public API query ray # %d to JSON', $this->id));
        }

        $filtered["payload"] = $this->_payload?->array();
        return $filtered;
    }
}
