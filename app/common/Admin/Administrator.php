<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\Buffer\Buffer;
use Comely\Security\Cipher;
use Comely\Security\Exception\CipherException;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Administrator
 * @package App\Common\Admin
 */
class Administrator extends AbstractAppModel
{
    public const TABLE = Administrators::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $status;
    /** @var string */
    public string $email;
    /** @var string|null */
    public ?string $phone;
    /** @var int */
    public int $timeStamp;

    /** @var Cipher|null */
    private ?Cipher $_cipher = null;
    /** @var Credentials|null */
    private ?Credentials $_cred = null;
    /** @var Privileges|null */
    private ?Privileges $_privileges = null;
    /** @var bool|null */
    private ?bool $_checksumValidated = null;

    use NotSerializableTrait;

    /**
     * @return Buffer
     * @throws AppException
     */
    public function checksum(): Buffer
    {
        try {
            $raw = sprintf("%d:%d:%s:%s", $this->id, $this->status, trim($this->email), trim($this->phone ?? ""));
            return $this->cipher()->pbkdf2("sha1", $raw, 1000);
        } catch (CipherException $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to calculate admin %d checksum', $this->id));
        }
    }

    /**
     * @return void
     * @throws AppException
     */
    public function validateChecksum(): void
    {
        if ($this->checksum()->raw() !== $this->private("checksum")) {
            throw new AppException(sprintf('Administration %d checksum validation failed', $this->id));
        }

        $this->_checksumValidated = true;
    }

    /**
     * @return bool
     */
    public function hasChecksumValidated(): bool
    {
        return (bool)$this->_checksumValidated;
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function cipher(): Cipher
    {
        if ($this->_cipher) {
            return $this->_cipher;
        }

        try {
            $this->_cipher = $this->aK->ciphers->primary()->remixChild(sprintf("admin_%d", $this->id));
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Cannot retrieve admin %d cipher', $this->id));
        }

        return $this->_cipher;
    }

    /**
     * @return Credentials
     * @throws AppException
     */
    public function credentials(): Credentials
    {
        if ($this->_cred) {
            return $this->_cred;
        }

        try {
            $credentials = $this->cipher()->decrypt(strval($this->private("credentials")));
            if (!$credentials instanceof Credentials) {
                throw new AppException('Administrator credentials object decrypt failed');
            }

            if ($credentials->adminId !== $this->id) {
                throw new AppException('Administrator and credentials IDs mismatch');
            }

            $this->_cred = $credentials;
            return $this->_cred;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt administrator %d credentials', $this->id)
            );
        }
    }

    /**
     * @return Privileges
     * @throws AppException
     */
    public function privileges(): Privileges
    {
        if ($this->_privileges) {
            return $this->_privileges;
        }

        $encrypted = $this->private("privileges");
        if (!is_string($encrypted) || !$encrypted) {
            $this->_privileges = new Privileges($this);
            return $this->_privileges;
        }

        try {
            $privileges = $this->cipher()->decrypt($encrypted);
            if (!$privileges instanceof Privileges) {
                throw new AppException('Administrator privileges object decrypt failed');
            }

            if ($privileges->adminId !== $this->id) {
                throw new AppException('Administrator and privileges IDs mismatch');
            }

            $this->_privileges = $privileges;
            return $this->_privileges;
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(
                sprintf('Failed to decrypt administrator %d privileges', $this->id)
            );
        }
    }
}
