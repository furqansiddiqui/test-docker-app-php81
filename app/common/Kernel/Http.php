<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use Comely\Http\Router;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Http
 * @package App\Common\Kernel
 */
class Http
{
    /** @var int */
    public readonly int $port;
    /** @var bool */
    public readonly bool $https;

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * @param Router $router
     */
    public function __construct(public readonly Router $router = new Router())
    {
        $this->port = intval($_SERVER["SERVER_PORT"] ?? 0);
        $this->https = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"]);
    }
}
