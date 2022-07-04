<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Security;
use Comely\Buffer\Buffer;
use Comely\Security\Cipher;
use Comely\Security\Exception\CipherException;

/**
 * Class User
 * @package App\Common\Users
 */
class User extends AbstractAppModel
{
    public const TABLE = Users::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $id;
    /** @var int|null */
    public ?int $referrerId = null;
    /** @var int */
    public int $groupId;
    /** @var int */
    public int $archived;
    /** @var string */
    public string $status;
    /** @var string */
    public string $username;
    /** @var string|null */
    public ?string $email = null;
    /** @var int */
    public int $emailVerified = 0;
    /** @var string|null */
    public ?string $phone = null;
    /** @var int */
    public int $phoneVerified = 0;
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
    /** @var string|null */
    public ?string $country = null;
    /** @var int */
    public int $createdOn;
    /** @var int */
    public int $updatedOn;

    /** @var Cipher|null */
    private ?Cipher $_cipher = null;
    /** @var bool|null */
    private ?bool $_checksumValidated = null;
    /** @var array */
    private array $_tags = [];

    /**
     * @return void
     */
    public function onLoad(): void
    {
        $this->_tags = explode(",", $this->private("tags") ?? "");
        parent::onLoad();
    }

    /**
     * @return void
     */
    public function onUnserialize()
    {
        $this->_tags = explode(",", $this->private("tags") ?? "");
        parent::onUnserialize();
    }

    /**
     * @return void
     */
    public function onSerialize()
    {
        $this->_cipher = null;
        $this->_checksumValidated = false;
        $this->_tags = [];
        parent::onSerialize();
    }

    /**
     * @return void
     */
    public function beforeQuery(): void
    {
        // Booleans correction
        $this->archived = $this->archived === 1 ? 1 : 0;
        $this->emailVerified = $this->emailVerified === 1 ? 1 : 0;
        $this->phoneVerified = $this->phoneVerified === 1 ? 1 : 0;
    }

    /**
     * @return Buffer
     * @throws AppException
     */
    public function checksum(): Buffer
    {
        $this->updateUserTags(); // Update tags associated with user account
        $raw = sprintf(
            "%d:%d:%d:%s:%d:%s:%s:%s:%d:%s:%d:%s:%d",
            $this->id,
            $this->referrerId > 0 ? $this->referrerId : 0,
            $this->groupId,
            $this->private("tags"),
            $this->archived === 1 ? 1 : 0,
            trim(strtolower($this->status)),
            trim(strtolower($this->username)),
            trim(strtolower($this->email ?? "")),
            $this->emailVerified === 1 ? 1 : 0,
            trim(strtolower($this->phone ?? "")),
            $this->phoneVerified === 1 ? 1 : 0,
            strtolower($this->country ?? ""),
            $this->createdOn
        );

        try {
            return $this->cipher()->pbkdf2("sha1", $raw, Security::PBKDF2_Iterations($this->id, static::TABLE));
        } catch (CipherException $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to compute user %d checksum', $this->id));
        }
    }

    /**
     * @return void
     * @throws AppException
     */
    public function validateChecksum(): void
    {
        if ($this->checksum()->raw() !== $this->private("checksum")) {
            throw new AppException(sprintf('User %d checksum validation failed', $this->id));
        }

        $this->_checksumValidated = true;
    }

    /**
     * @return bool
     */
    public function isChecksumValidated(): bool
    {
        return (bool)$this->_checksumValidated;
    }

    /**
     * @return array
     */
    public function tags(): array
    {
        return $this->_tags;
    }

    /**
     * @param string $tag
     * @return bool
     */
    public function hasTag(string $tag): bool
    {
        return in_array(strtolower(trim($tag)), $this->_tags);
    }

    /**
     * @param string $tag
     * @return bool
     */
    public function deleteTag(string $tag): bool
    {
        $index = array_search(strtolower(trim($tag)), $this->_tags);
        if (is_int($index) && $index >= 0) {
            unset($this->_tags[$index]);
            $this->updateUserTags();
            return true;
        }

        return false;
    }

    /**
     * @param string $tag
     * @return bool
     */
    public function appendTag(string $tag): bool
    {
        $tag = strtoupper(trim($tag));
        if (!$this->hasTag($tag)) {
            $this->_tags[] = $tag;
            $this->updateUserTags();
            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    private function updateUserTags(): void
    {
        $tagsStr = implode(",", array_unique($this->_tags));
        if (strlen($tagsStr) > 512) {
            throw new \RuntimeException(sprintf('User %d account tags exceeding limit of 512 bytes', $this->id));
        }

        $this->set("tags", $tagsStr);
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
            $this->_cipher = $this->aK->ciphers->primary()->remixChild(sprintf("user_%d", $this->id));
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Cannot retrieve user %d cipher', $this->id));
        }

        return $this->_cipher;
    }

    /**
     * @return void
     * @throws \Comely\Cache\Exception\CacheException
     */
    public function deleteCached(): void
    {
        $cache = $this->aK->cache;
        $cache->delete(sprintf("user_%d", $this->id));

        if ($this->email) {
            $cache->delete(sprintf("user_em_%s", md5(strtolower(trim($this->email)))));
        }

        if ($this->phone) {
            $cache->delete(sprintf("user_ph_%s", md5(strtolower(trim($this->phone)))));
        }
    }
}
