<?php
declare(strict_types=1);

namespace App\Common\Kernel\ErrorHandler;

use Comely\Filesystem\File;

/**
 * Class StdErrorHandler
 * @package App\Common\Kernel\ErrorHandler
 */
class StdErrorHandler extends AbstractErrorHandler
{
    /** @var File|null */
    private ?File $errorLogFile = null;

    /**
     * @param ErrorMsg $err
     * @return bool
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\FilesystemException
     */
    public function handleError(ErrorMsg $err): bool
    {
        $this->aK->errors->append($err);

        // Error buffering
        $buffer[] = "";
        $buffer[] = sprintf("\e[36m[%s]\e[0m", date("d-m-Y H:i"));
        $buffer[] = sprintf("\e[33mError:\e[0m \e[31m%s\e[0m", $err->typeStr);
        $buffer[] = sprintf("\e[33mMessage:\e[0m %s", $err->message);
        $buffer[] = sprintf("\e[33mFile:\e[0m \e[34m%s\e[0m", $err->file);
        $buffer[] = sprintf("\e[33mLine:\e[0m \e[36m%d\e[0m", $err->line);

        $includeTrace = $err->type <= $this->traceLevel;
        if ($includeTrace) {
            $trace = array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 3);
            $this->bufferTrace($buffer, $trace);
        }

        $buffer[] = "";
        $this->writeBuffer($buffer);

        // Handling
        if (!in_array($err->type, [2, 8, 512, 1024, 2048, 8192, 16384])) {
            exit(json_encode(["FatalError" => [$err->typeStr, $err->message]]));
        }

        return true;
    }

    /**
     * @param \Throwable $t
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\FilesystemException
     */
    public function handleThrowable(\Throwable $t): never
    {
        // Error Buffering
        $buffer[] = "";
        $buffer[] = str_repeat(".", 10);
        $buffer[] = "";
        $buffer[] = sprintf("\e[36m[%s]\e[0m", date("d-m-Y H:i"));
        $buffer[] = sprintf("\e[33mCaught:\e[0m \e[31m%s\e[0m", get_class($t));
        $buffer[] = sprintf("\e[33mMessage:\e[0m %s", $t->getMessage());
        $buffer[] = sprintf("\e[33mFile:\e[0m \e[34m%s\e[0m", $this->filePath($t->getFile()));
        $buffer[] = sprintf("\e[33mLine:\e[0m \e[36m%d\e[0m", $t->getLine());
        $this->bufferTrace($buffer, $t->getTrace());
        $buffer[] = "";
        $buffer[] = str_repeat(".", 10);
        $buffer[] = "";
        $this->writeBuffer($buffer);

        // Handling
        exit(json_encode(["FatalError" => [get_class($t), $t->getMessage(), $t->getCode(), $t->getTraceAsString()]]));
    }

    /**
     * @param array $buffer
     * @return void
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Filesystem\Exception\FilesystemException
     */
    private function writeBuffer(array $buffer): void
    {
        if (!$this->errorLogFile) {
            $this->errorLogFile = $this->aK->dirs->log()->file("error.log", true);
        }

        $this->errorLogFile->append(implode(PHP_EOL, $buffer));
    }

    /**
     * @param array $buffer
     * @param array $trace
     */
    private function bufferTrace(array &$buffer, array $trace): void
    {
        if (!$trace) {
            return;
        }

        $buffer[] = "\e[33mBacktrace:\e[0m";
        $buffer[] = "┬";
        foreach ($trace as $sf) {
            $function = $sf["function"] ?? null;
            $class = $sf["class"] ?? null;
            $type = $sf["type"] ?? null;
            $file = $sf["file"] ?? null;
            $line = $sf["line"] ?? null;

            if ($file && is_string($file) && $line) {
                $file = $this->filePath($file);
                $method = $function;
                if ($class && $type) {
                    $method = $class . $type . $function;
                }

                $traceString = sprintf("\e[4m\e[36m%s\e[0m on line # \e[4m\e[33m%d\e[0m", $file, $line);
                if ($method) {
                    $traceString = sprintf("Method \e[4m\e[35m%s\e[0m in file ", $method) . $traceString;
                }

                $buffer[] = "├─ " . $traceString;
            }
        }
    }
}
