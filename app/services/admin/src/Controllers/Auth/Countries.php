<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Countries\CachedCountriesList;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Database\Schema;

/**
 * Class Countries
 * @package App\Services\Admin\Controllers\Auth
 */
class Countries extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    protected function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
    }

    private function getCountry(): void
    {

    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Database\Exception\SchemaTableException
     */
    private function getList(): void
    {
        $useCache = true;
        if ($this->input()->has("cache")) {
            if ($this->input()->getUnsafe("cache") === false) {
                $useCache = false;
            }
        }

        $availableOnly = match (trim(strtolower($this->input()->getASCII("status")))) {
            "available" => true,
            "disabled" => false,
            default => null,
        };

        $countries = CachedCountriesList::getInstance(useCache: $useCache, availableOnly: $availableOnly);

        $this->status(true);
        $this->response->set("countries", $countries);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function get(): void
    {
        $action = trim(strtolower($this->input()->getASCII("action")));
        switch ($action) {
            case "list":
                $this->getList();
                return;
            case "country":
                $this->getCountry();
                return;
            default:
                throw new AdminAPIException('Invalid value for "action" param');
        }
    }
}
