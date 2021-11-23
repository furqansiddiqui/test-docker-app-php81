<?php
declare(strict_types=1);

namespace App\Common\Kernel\Traits;

/**
 * Prevent var_dump of implementing classes
 */
trait NoDumpTrait
{
    /**
     * @return array
     */
    final public function __debugInfo(): array
    {
        return [get_called_class()];
    }
}
