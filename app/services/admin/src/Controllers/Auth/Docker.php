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
     * @param string $ipAddress
     * @param int $counts
     * @return bool
     */
    private function pingService(string $ipAddress, int $counts = 5): bool
    {
        exec(sprintf("ping -c %d %s", $counts, $ipAddress), result_code: $resultCode);
        return $resultCode === 0;
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
        $dockerConfig = DockerConfig::getInstance($this->aK, useCache: true);

        // Ping services?
        $pingCheck = strtolower($this->input()->getASCII("ping"));
        $pingCheck = $pingCheck && Validator::getBool($pingCheck);

        // Specific service info requested?
        $serviceId = strtolower($this->input()->getASCII("service"));
        if ($serviceId) {
            $dockerService = null;
            /** @var DockerConfig\DockerConfigService $service */
            foreach ($dockerConfig->services as $service) {
                if (strtolower($service->id) === $serviceId) {
                    $dockerService = $service;
                    break;
                }
            }

            if (!$dockerService) {
                throw new AdminAPIException('Requested docker service does not exist');
            }

            // PING for status
            if ($pingCheck && $service->ipAddress) {
                $service->status = $this->pingService($service->ipAddress, 2);
            }

            // JSON encode
            try {
                $dockerService = Validator::JSON_Filter($dockerService);
            } catch (\JsonException) {
                throw new AdminAPIException('Failed to encode DockerConfigService object');
            }

            $this->status(true);
            $this->response->set("service", $dockerService);
            return;
        }

        // PING all services
        if ($pingCheck) {
            /** @var DockerConfig\DockerConfigService $service */
            foreach ($dockerConfig->services as $service) {
                if ($service->ipAddress) {
                    $service->status = $this->pingService($service->ipAddress, 2);
                }
            }
        }

        // JSON encode
        try {
            $dockerConfig = Validator::JSON_Filter($dockerConfig);
        } catch (\JsonException) {
            throw new AdminAPIException('Failed to encode DockerConfig object');
        }

        $this->status(true);
        $this->response->set("docker", $dockerConfig);
    }
}
