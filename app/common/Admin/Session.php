<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Admin\Sessions;
use App\Common\Exception\AppException;
use Comely\Buffer\Buffer;

/**
 * Class Session
 * @package App\Common\Admin
 * @property bool $checksumHealth
 */
class Session extends AbstractAppModel
{
    public const TABLE = Sessions::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $type;
    /** @var int */
    public int $archived;
    /** @var int */
    public int $adminId;
    /** @var string */
    public string $ipAddress;
    /** @var string|null */
    public ?string $last2faCode = null;
    /** @var int|null */
    public ?int $last2faOn = null;
    /** @var int */
    public int $issuedOn;
    /** @var int */
    public int $lastUsedOn;

    /**
     * @return Buffer
     * @throws AppException
     */
    public function checksum(): Buffer
    {
        $token = $this->private("token");
        if (!is_string($token) || strlen($token) !== 32) {
            throw new \UnexpectedValueException('Session token not set for checksum; or is not 32 bytes');
        }

        $raw = sprintf(
            "%d:%s:%s:%d:%s:%d:%d",
            $this->id,
            strtolower($this->type),
            $token,
            $this->adminId,
            strtolower($this->ipAddress),
            $this->issuedOn,
            $this->lastUsedOn
        );

        try {
            return $this->aK->ciphers->primary()->pbkdf2("sha1", $raw, 1000 + $this->adminId);
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e);
            throw new AppException(sprintf('Failed to compute admin session %d checksum', $this->id));
        }
    }
}
