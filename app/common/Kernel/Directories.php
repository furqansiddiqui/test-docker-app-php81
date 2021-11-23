<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\Exception\AppDirException;
use Comely\Filesystem\Directory;
use Comely\Filesystem\Exception\FilesystemException;
use Comely\Filesystem\Exception\PathNotExistException;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Directories
 * @package App\Common\Kernel
 */
class Directories
{
    /** @var Directory */
    private Directory $root;
    /** @var Directory|null */
    private ?Directory $config = null;
    /** @var Directory|null */
    private ?Directory $storage = null;
    /** @var Directory|null */
    private ?Directory $log = null;
    /** @var Directory|null */
    private ?Directory $tmp = null;
    /** @var Directory|null */
    private ?Directory $semaphore = null;
    /** @var Directory|null */
    private ?Directory $emails = null;

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * @throws \Comely\Filesystem\Exception\PathException
     * @throws \Comely\Filesystem\Exception\PathNotExistException
     */
    public function __construct()
    {
        $this->root = new Directory(dirname(__FILE__, 3));
    }

    /**
     * @return Directory
     */
    public function root(): Directory
    {
        return $this->root;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function config(): Directory
    {
        if (!$this->config) {
            $this->config = $this->dir("config", "/config", false);
        }

        return $this->config;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function storage(): Directory
    {
        if (!$this->storage) {
            $this->storage = $this->dir("storage", "/storage", true);
        }

        return $this->storage;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function tmp(): Directory
    {
        if (!$this->tmp) {
            $this->tmp = $this->dir("tmp", "/tmp", true);
        }

        return $this->tmp;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function sempahore(): Directory
    {
        if (!$this->semaphore) {
            $this->semaphore = $this->dir("semaphore", "/tmp/semaphore", true);
        }

        return $this->semaphore;
    }

    /**
     * @return Directory
     * @throws AppDirException
     */
    public function emails(): Directory
    {
        if (!$this->emails) {
            $this->emails = $this->dir("emails", "/emails", true);
        }

        return $this->emails;
    }

    /**
     * @param string $prop
     * @param string $path
     * @param bool $checkWritable
     * @return Directory
     * @throws AppDirException
     */
    private function dir(string $prop, string $path, bool $checkWritable): Directory
    {
        try {
            $dir = $this->root->dir($path);
            if (!$dir->permissions()->readable()) {
                throw new AppDirException(sprintf('App directory [:%s] is not readable', $prop));
            }

            if ($checkWritable && !$dir->permissions()->writable()) {
                throw new AppDirException(sprintf('App directory [:%s] is not writable', $prop));
            }
        } catch (PathNotExistException) {
            throw new AppDirException(sprintf('App directory [:%s] does not exist', $prop));
        } catch (FilesystemException $e) {
            throw new AppDirException(
                sprintf('App directory [:%s]: [%s] %s', $prop, get_class($e), $e->getMessage())
            );
        }

        return $dir;
    }
}
