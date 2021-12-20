<?php
declare(strict_types=1);

namespace App\Common\Database;

use App\Common\AppKernel;
use Comely\Database\Schema\ORM\Abstract_ORM_Model;

/**
 * Class AbstractAppModel
 * @package App\Common\Database
 */
abstract class AbstractAppModel extends Abstract_ORM_Model
{
    /** @var AppKernel|null */
    protected ?AppKernel $aK = null;

    /**
     * @return void
     */
    public function onConstruct()
    {
        $this->aK = AppKernel::getInstance();
    }

    /**
     * @return void
     */
    public function onLoad()
    {
        $this->aK = AppKernel::getInstance();
    }

    /**
     * @return void
     */
    public function onSerialize()
    {
        $this->aK = null;
    }

    /**
     * @return void
     */
    public function onUnserialize()
    {
        $this->aK = AppKernel::getInstance();
    }
}
