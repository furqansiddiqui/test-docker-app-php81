<?php
declare(strict_types=1);

namespace App\Services\Public\Controllers;

use App\Services\Public\AbstractPublicAPIController;

/**
 * Class Hello
 * @package App\Services\Public\Controllers
 */
class Hello extends AbstractPublicAPIController
{
    protected function publicAPICallback(): void
    {
    }

    /**
     * @return void
     */
    protected function get(): void
    {
        $this->status(true);
        $this->response->set("message", "Hello World!");
    }
}
