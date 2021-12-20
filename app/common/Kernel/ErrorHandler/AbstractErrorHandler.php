<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use App\Common\AppKernel;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class AbstractErrorHandler
 * @package App\Common\Kernel\ErrorHandler
 */
abstract class AbstractErrorHandler
{
    /** @var int */
    protected int $pathOffset;
    /** @var int */
    protected int $traceLevel;

    use NoDumpTrait;
    use NotSerializableTrait;
    use NotCloneableTrait;

    /**
     * @param ErrorMsg $err
     * @return bool
     */
    abstract public function handleError(ErrorMsg $err): bool;

    /**
     * @param \Throwable $t
     * @return never
     */
    abstract public function handleThrowable(\Throwable $t): never;

    /**
     * @param AppKernel $aK
     */
    final public function __construct(protected AppKernel $aK)
    {
        $this->pathOffset = strlen($aK->dirs->root->path());
        $this->setTraceLevel(E_WARNING);

        set_error_handler([$this, "errorHandler"]);
        set_exception_handler([$this, "handleThrowable"]);
    }

    /**
     * @param int $lvl
     */
    final public function setTraceLevel(int $lvl): void
    {
        if ($lvl < 0 || $lvl > 0xffff) {
            throw new \InvalidArgumentException('Invalid trace level');
        }

        $this->traceLevel = $lvl;
    }

    /**
     * @return int
     */
    public function traceLevel(): int
    {
        return $this->traceLevel;
    }

    /**
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     */
    final public function errorHandler(int $type, string $message, string $file, int $line): bool
    {
        if (error_reporting() === 0) return false;

        $err = new ErrorMsg();
        $err->type = $type;
        $err->typeStr = $this->errorTypeStr($type);
        $err->message = $message;
        $err->file = $this->filePath($file);
        $err->line = $line;
        $err->triggered = true;

        return $this->handleError($err);
    }

    /**
     * @param string $path
     * @return string
     */
    final public function filePath(string $path): string
    {
        return trim(substr($path, $this->pathOffset), DIRECTORY_SEPARATOR);
    }

    /**
     * @param int $type
     * @return string
     */
    final public function errorTypeStr(int $type): string
    {
        return match ($type) {
            1 => "Fatal Error",
            2, 512 => "Warning",
            4 => "Parse Error",
            8, 1024 => "Notice",
            16 => "Core Error",
            32 => "Core Warning",
            64 => "Compile Error",
            128 => "Compile Warning",
            256 => "Error",
            2048 => "Strict",
            4096 => "Recoverable",
            8192, 16384 => "Deprecated",
            default => "Unknown",
        };
    }
}
