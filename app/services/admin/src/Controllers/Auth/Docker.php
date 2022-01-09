<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Services\Admin\Controllers\AbstractAdminAPIController;
use App\Services\Admin\Models\DockerConfig;

/**
 * Class Docker
 * @package App\Services\Admin\Controllers\Auth
 */
class Docker extends AbstractAdminAPIController
{
    protected function adminAPICallback(): void
    {
        // Todo: change to authenticated API type
    }

    protected function get(): void
    {
        $dockerConfig = new DockerConfig($this->aK);
    }
}
