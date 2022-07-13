<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Admin;

use App\Common\Admin\Administrator;
use App\Common\Admin\Log;
use App\Common\AppKernel;
use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Logs
 * @package App\Common\Database\Primary\Admin
 */
class Logs extends AbstractAppTable
{
    public const TABLE = "a_logs";
    public const ORM_CLASS = 'App\Common\Admin\Log';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->int("admin")->bytes(4)->unSigned();
        $cols->string("flags")->length(255)->nullable();
        $cols->string("controller")->length(255)->nullable();
        $cols->string("log")->length(255);
        $cols->string("ip_address")->length(45);
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("admin")->table(Administrators::TABLE, "id");
    }

    /**
     * @param Administrator $admin
     * @param string $ipAddress
     * @param string $message
     * @param string|null $controller
     * @param int|null $line
     * @param array $flags
     * @return Log
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public static function Insert(
        Administrator $admin,
        string        $ipAddress,
        string        $message,
        ?string       $controller = null,
        ?int          $line = null,
        array         $flags = []
    ): Log
    {
        if (!preg_match('/^\w+[\w\s@\-:=+.#\",()\[\];]+$/', $message)) {
            throw new AppException('Admin log contains an illegal character');
        } elseif (strlen($message) > 255) {
            throw new AppException('Admin log cannot exceed 255 bytes');
        }

        $logFlags = null;
        if ($flags) {
            $logFlags = [];
            $flagIndex = -1;
            foreach ($flags as $flag) {
                $flagIndex++;
                if (!preg_match('/^[\w.\-]{1,16}(:\d{1,10})?$/', $flag)) {
                    throw new AppException(sprintf('Invalid admin log flag at index %d', $flagIndex));
                }

                $logFlags[] = $flag;
            }

            $logFlags = implode(",", $logFlags);
            if (strlen($logFlags) > 255) {
                throw new AppException('Admin log flags exceed limit of 255 bytes');
            }
        }

        if ($controller && $line) {
            $controller = $controller . "#" . $line;
        }

        $db = AppKernel::getInstance()->db->primary();

        // Prepare Log model
        $log = new Log();
        $log->id = 0;
        $log->admin = $admin->id;
        $log->flags = $logFlags;
        $log->controller = $controller;
        $log->log = $message;
        $log->ipAddress = $ipAddress;
        $log->timeStamp = time();
        $log->query()->insert();
        $log->id = $db->lastInsertId();
        return $log;
    }
}
