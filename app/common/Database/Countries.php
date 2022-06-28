<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Countries
 * @package App\Common\Database\Primary
 */
class Countries extends AbstractAppTable
{
    public const TABLE = "countries";
    public const ORM_CLASS = 'App\Common\Country';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("available")->bytes(1)->default(0);
        $cols->string("name")->length(32);
        $cols->string("code")->fixed(3)->unique();
        $cols->string("code_short")->fixed(2)->unique();
        $cols->int("dial_code")->bytes(3)->unSigned();
    }
}
