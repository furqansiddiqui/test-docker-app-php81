<?php
declare(strict_types=1);

namespace App\Common\Database;

use App\Common\AppKernel;
use Comely\Database\Schema\AbstractDbTable;

/**
 * Class AbstractAppTable
 * @package App\Common\Database
 */
abstract class AbstractAppTable extends AbstractDbTable
{
    /** @var AppKernel */
    protected readonly AppKernel $aK;

    /**
     * @return void
     */
    protected function onConstruct(): void
    {
        $this->aK = AppKernel::getInstance();
    }
}
