<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users\Groups;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Users
 * @package App\Common\Database\Primary
 */
class Users extends AbstractAppTable
{
    public const TABLE = "users";
    public const ORM_CLASS = 'App\Common\Users\User';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->int("group_id")->bytes(4)->unSigned();
        $cols->int("archived")->bytes(1)->default(0);
        $cols->enum("status")->options("active", "disabled")->default("active");
        $cols->string("username")->length(16)->unique();
        $cols->string("email")->length(64)->nullable()->unique();
        $cols->int("email_verified")->bytes(1)->default(0);
        $cols->string("phone")->length(32)->nullable();
        $cols->int("phone_verified")->bytes(1)->default(0);
        $cols->string("first_name")->length(32)
            ->charset("utf8mb4")->collation("utf8mb4_general_ci");
        $cols->string("last_name")->length(32)
            ->charset("utf8mb4")->collation("utf8mb4_general_ci");
        $cols->string("country")->fixed(3)->nullable();
        $cols->binary("credentials")->length(4096); // 4 KB encrypted credentials object
        $cols->binary("params")->length(6144); // 6 KB encrypted params object
        $cols->binary("web_auth_token")->fixed(48)->nullable(); // 32 bytes session ID + 16 bytes hmac secret
        $cols->binary("app_auth_token")->fixed(48)->nullable(); // 32 bytes session ID + 16 bytes hmac secret
        $cols->int("created_on")->bytes(4)->unSigned();
        $cols->int("updated_on")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("group")->table(Groups::TABLE, "id");
        $constraints->foreignKey("country")->table(Countries::TABLE, "code");
    }
}
