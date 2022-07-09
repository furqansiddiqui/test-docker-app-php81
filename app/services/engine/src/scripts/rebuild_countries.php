<?php
declare(strict_types=1);

namespace bin;

use App\Common\Database\Primary\Countries;
use App\Common\Exception\AppException;
use App\Common\Kernel\CLI\AbstractCLIScript;

/**
 * Class rebuild_countries
 * @package bin
 */
class rebuild_countries extends AbstractCLIScript
{
    /**
     * @return void
     * @throws AppException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public function exec(): void
    {
        // Read Data File
        $this->print('Looking for {yellow}{b}Countries TSV{/} file...');
        $countriesTSVPath = $this->aK->dirs->storage()->suffix("data/countries.tsv");
        $this->inline(sprintf('Path: {cyan}%s{/} ... ', $countriesTSVPath));
        if (!@is_file($countriesTSVPath)) {
            $this->print('{red}Not Found{/}');
            throw new AppException('Countries TSV files not found in path');
        }

        if (!@is_readable($countriesTSVPath)) {
            $this->print('{red}Not Readable{/}');
            throw new AppException('Countries TSV file is not readable');
        }

        $this->print('{green}OK{/}');

        $countriesTSV = file_get_contents($countriesTSVPath);
        if (!$countriesTSVPath) {
            throw new AppException('Failed to read countries TSV file');
        }

        $countriesTSV = preg_split('(\r\n|\n|\r)', trim($countriesTSV));
        $this->print("");
        $this->print(sprintf("Total Countries Found: {green}{invert}%s{/}", count($countriesTSV)));

        $db = $this->aK->db->primary();
        foreach ($countriesTSV as $country) {
            $country = explode("\t", $country);
            if (!$country) {
                throw new AppException('Failed to read a country line');
            }

            $saveCountryQuery = 'INSERT ' . 'INTO `%s` (`available`, `name`, `code`, `code_short`, `dial_code`) ' .
                'VALUES (:available, :name, :code, :codeShort, :dialCode) ON DUPLICATE KEY UPDATE `name`=:name, ' .
                '`code`=:code, `code_short`=:codeShort, `dial_code`=:dialCode';
            $saveCountryData = [
                "available" => 1,
                "name" => $country[0],
                "code" => $country[2],
                "codeShort" => $country[1],
                "dialCode" => $country[3] ?? null
            ];

            $this->inline(sprintf('%s {cyan}%s{/} ... ', $saveCountryData["name"], $saveCountryData["code"]));
            $saveCountryQuery = $db->exec(sprintf($saveCountryQuery, Countries::TABLE), $saveCountryData);
            if ($saveCountryQuery->isSuccess(false)) {
                $this->print('{green}SUCCESS{/}');
            } else {
                $this->print('{red}FAIL{/}');
            }

            unset($country, $saveCountryQuery, $saveCountryData);
        }
    }

    /**
     * @return string|null
     */
    public function processInstanceId(): ?string
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function semaphoreLockId(): ?string
    {
        return null;
    }
}
