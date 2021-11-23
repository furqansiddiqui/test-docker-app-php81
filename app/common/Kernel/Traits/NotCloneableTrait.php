<?php
declare(strict_types=1);

namespace App\Common\Kernel\Traits;

/**
 * Prevents implementing classes/objects from being cloned
 */
trait NotCloneableTrait
{
    /**
     * Throws \RuntimeException preventing the clone
     */
    final public function __clone(): void
    {
        throw new \RuntimeException(get_called_class() . ' instance cannot be cloned');
    }
}
