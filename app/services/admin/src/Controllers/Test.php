<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers;

use App\Services\Admin\AbstractAdminAPIController;

/**
 * Class Test
 * @package App\Services\Admin\Controllers
 */
class Test extends AbstractAdminAPIController
{
    protected function adminAPICallback(): void
    {
    }

    protected function get(): void
    {
        $this->status(true);
    }
}
