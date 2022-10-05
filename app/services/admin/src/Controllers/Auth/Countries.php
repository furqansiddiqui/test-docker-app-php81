<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Countries\CachedCountriesList;
use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Database\Schema;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Countries
 * @package App\Services\Admin\Controllers\Auth
 */
class Countries extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DatabaseException
     */
    protected function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Countries');
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function changeStatusList(): void
    {
        // List
        $list = trim(strtolower($this->input()->getASCII("list")));
        if (!in_array($list, ["available", "disabled"])) {
            throw AdminAPIException::Param("list", "Invalid value for list param");
        }

        // Countries
        try {
            $countries = explode(",", trim(trim($this->input()->getASCII("countries")), ","));
            if (!$countries) {
                throw new AdminAPIException('There are no selected countries');
            }

            foreach ($countries as $countryCode) {
                if (!preg_match('/^[a-z]{3}$/i', $countryCode)) {
                    throw new AdminAPIException('Countries list contains an invalid country name');
                }
            }
        } catch (AdminAPIException $e) {
            $e->setParam("countries");
            throw $e;
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"), allowReuse: true);

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $queryStr = sprintf(
                'UPDATE ' . '`%s` SET `available`=? WHERE `code` IN (%s)',
                \App\Common\Database\Primary\Countries::TABLE,
                implode(",", array_map(function ($code) {
                    return "'" . $code . "'";
                }, $countries))
            );

            $query = $db->exec($queryStr, [$list === "available" ? 1 : 0]);
            $moveCount = $query->rows();
            if (!$moveCount) {
                throw new AdminAPIException('No changes were made');
            }

            $this->adminLogEntry(
                sprintf('%d countries moved to %s list', $moveCount, strtoupper($list)),
                flags: ["config", "countries"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Delete Cached Instances
        try {
            CachedCountriesList::Purge(purgeAll: true);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
        $this->response->set("count", $moveCount);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function setupCountry(): void
    {
        $nameValidator = Validator::Name(32, false);

        // Status
        $list = strtolower(trim($this->input()->getASCII("list")));
        if (!in_array($list, ["available", "disabled"])) {
            throw AdminAPIException::Param("list", "Invalid country list selection");
        }

        // Name
        try {
            $name = $nameValidator->getValidated($this->input()->getUnsafe("name"));
        } catch (ValidatorException $e) {
            $errorMsg = match ($e->getCode()) {
                \Comely\Utils\Validator\Validator::LENGTH_UNDERFLOW_ERROR,
                \Comely\Utils\Validator\Validator::LENGTH_OVERFLOW_ERROR => 'Name must be between 3 and 32 characters long',
                \Comely\Utils\Validator\Validator::REGEX_MATCH_ERROR => 'Country name contains illegal character',
                default => 'Invalid country name'
            };

            throw AdminAPIException::Param("name", $errorMsg);
        }

        // Code ISO 3166-1 / Alpha-3
        $code = strtoupper(trim($this->input()->getASCII("code")));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            throw AdminAPIException::Param("code", "Invalid ISO 3166-1 Alpha-3 code");
        }

        // Alpha-2
        $codeShort = strtoupper(trim($this->input()->getASCII("codeShort")));
        if (!preg_match('/^[A-Z]{2}$/', $codeShort)) {
            throw AdminAPIException::Param("codeShort", "Invalid ISO 3166-1 Alpha-2 code");
        }

        // Dial Code
        $dialCode = $this->input()->getInt("dialCode", unSigned: true);
        if ($dialCode < 1 || $dialCode > 0xffffff) {
            throw AdminAPIException::Param("dialCode", "Invalid international dialing code");
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"), allowReuse: true);

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $queryStr = sprintf(
                'INSERT ' . 'INTO `%s` (`available`, `name`, `code`, `code_short`, `dial_code`) VALUES (:available, ' .
                ':name, :code, :codeShort, :dialCode) ON DUPLICATE KEY UPDATE `available`=:available, `name`=:name, ' .
                '`code`=:code, `code_short`=:codeShort, `dial_code`=:dialCode',
                \App\Common\Database\Primary\Countries::TABLE
            );

            $query = $db->exec($queryStr, [
                "available" => $list === "available" ? 1 : 0,
                "name" => $name,
                "code" => $code,
                "codeShort" => $codeShort,
                "dialCode" => $dialCode
            ]);

            $moveCount = $query->rows();
            if (!$moveCount) {
                throw new AdminAPIException('No changes were made');
            }

            $this->adminLogEntry(
                sprintf('Country %s details updated', $code),
                flags: ["config", "countries"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Delete Cached Instances
        try {
            CachedCountriesList::Purge(purgeAll: true);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        try {
            \App\Common\Database\Primary\Countries::DeleteCached($code);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
        $this->response->set("count", $moveCount);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            if (!$this->admin->privileges()->editConfig) {
                throw new AdminAPIException('You do not have privilege to edit configuration');
            }
        }

        $action = trim(strtolower($this->input()->getASCII("action")));
        switch ($action) {
            case "status":
                $this->changeStatusList();
                return;
            case "setup":
                $this->setupCountry();
                return;
            default:
                throw AdminAPIException::Param("action", "Invalid action called");
        }
    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Database\Exception\SchemaTableException
     */
    private function getList(): void
    {
        $countries = \App\Common\Database\Primary\Countries::Find()->query('WHERE 1 ORDER BY `name` ASC')->all();

        $this->status(true);
        $this->response->set("countries", [
            "count" => count($countries),
            "countries" => $countries
        ]);
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
            default:
                throw new AdminAPIException('Invalid value for "action" param');
        }
    }
}
