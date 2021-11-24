<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use App\Common\AppKernel;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Errors
 * @package App\Common\Kernel\ErrorHandler
 */
class Errors
{
    /** @var ErrorLog */
    private ErrorLog $triggered;
    /** @var ErrorLog */
    private ErrorLog $logged;

    use NoDumpTrait;
    use NotSerializableTrait;
    use NotCloneableTrait;

    /**
     * @param \Throwable $e
     * @param int $type
     */
    public static function Exception2Error(\Throwable $e, int $type = E_USER_WARNING): void
    {
        trigger_error(self::Exception2String($e), $type);
    }

    /**
     * @param \Throwable $e
     * @return string
     */
    public static function Exception2String(\Throwable $e): string
    {
        return sprintf('[%s][#%s] %s', get_class($e), $e->getCode(), $e->getMessage());
    }

    /**
     * @param AppKernel $aK
     */
    final public function __construct(private AppKernel $aK)
    {
        $this->triggered = new ErrorLog();
        $this->logged = new ErrorLog();
    }

    /**
     * Flush error logs
     */
    final public function flush(): void
    {
        $this->triggered->flush();
        $this->logged->flush();
    }

    /**
     * @param $message
     * @param int $type
     * @param int $traceLevel
     */
    final public function trigger($message, int $type = E_USER_NOTICE, int $traceLevel = 1): void
    {
        $errorMsg = $this->prepareErrorMsg($message, $type, $traceLevel);
        $errorMsg->triggered = true;
        $this->aK->errorHandler()->handleError($errorMsg);
    }

    /**
     * @param $message
     * @param int $type
     * @param int $traceLevel
     */
    final public function triggerIfDebug($message, int $type = E_USER_NOTICE, int $traceLevel = 1): void
    {
        $errorMsg = $this->prepareErrorMsg($message, $type, $traceLevel);
        $errorMsg->triggered = $this->aK->isDebug();
        $this->aK->errorHandler()->handleError($errorMsg);
    }

    /**
     * @param string|\Throwable $message
     * @param int $type
     * @param int $traceLevel
     * @return ErrorMsg
     */
    private function prepareErrorMsg(string|\Throwable $message, int $type = E_USER_NOTICE, int $traceLevel = 1): ErrorMsg
    {
        if ($message instanceof \Throwable) {
            $message = self::Exception2String($message);
        }

        if (!is_string($message)) {
            throw new \InvalidArgumentException(sprintf('Cannot create ErrorMsg from arg type "%s"', gettype($message)));
        }

        if (!in_array($type, [E_USER_NOTICE, E_USER_WARNING])) {
            throw new \InvalidArgumentException('Invalid triggered error type');
        }

        $eH = $this->aK->errorHandler();
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $error = new ErrorMsg();
        $error->type = $type;
        $error->typeStr = $eH->errorTypeStr($type);
        $error->message = $message;
        $error->file = $eH->filePath($trace[$traceLevel]["file"] ?? "");
        $error->line = intval($trace[$traceLevel]["line"] ?? -1);
        return $error;
    }

    /**
     * @param ErrorMsg $error
     */
    final public function append(ErrorMsg $error): void
    {
        if ($error->triggered) {
            $this->triggered->append($error);
        } else {
            $this->logged->append($error);
        }
    }

    /**
     * @return ErrorLog
     */
    final public function triggered(): ErrorLog
    {
        return $this->triggered;
    }

    /**
     * @return ErrorLog
     */
    final public function logged(): ErrorLog
    {
        return $this->logged;
    }

    /**
     * @return int
     */
    final public function count(): int
    {
        return $this->triggered->count() + $this->logged->count();
    }

    /**
     * @return array
     */
    final public function all(): array
    {
        return array_merge($this->triggered->array(), $this->logged->array());
    }
}
