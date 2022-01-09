<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Kernel\Docker\DockerConfig;
use App\Common\Validator;
use App\Services\Admin\Controllers\AbstractAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;

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

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\DockerConfigException
     * @throws \Comely\Filesystem\Exception\PathException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function get(): void
    {
        try {
            $dockerConfig = Validator::JSON_Filter(DockerConfig::getInstance($this->aK, useCache: true));
        } catch (\JsonException) {
            throw new AdminAPIException('Failed to encode DockerConfig object');
        }

        $this->status(true);
        $this->response->set("docker", $dockerConfig);
    }
}
