<?php
declare(strict_types=1);

namespace App\Services\Admin\Models {

    use App\Common\AppKernel;
    use App\Services\Admin\Models\DockerConfig\DockerConfigNetwork;

    /**
     * Class DockerConfig
     * @package App\Services\Admin\Models
     */
    class DockerConfig
    {
        public DockerConfigNetwork $network;

        public function __construct(AppKernel $aK)
        {
            $files = $aK->dirs->root->dir("docker")->glob("*.yml");
            var_dump($files);
        }
    }
}

namespace App\Services\Admin\Models\DockerConfig {
    /**
     * Class DockerConfigNetwork
     * @package App\Services\Admin\Models
     */
    class DockerConfigNetwork
    {
        /** @var string */
        public string $name;
        /** @var string */
        public string $driver;
        /** @var string */
        public string $subnet;
    }


    class DockerConfig
    {

    }
}
