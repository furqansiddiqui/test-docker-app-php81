<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\AppKernel;
use App\Common\Database\AbstractAppTable;
use App\Common\Validator;
use Comely\Buffer\Buffer;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class DataStore
 * @package App\Common\Database\Primary
 */
class DataStore extends AbstractAppTable
{
    public const NAME = 'd_storage';
    public const MODEL = null;
    public const DATA_MAX_BYTES = 10240; // 10 KiB

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->string("key")->length(40)->unique();
        $cols->binary("data")->length(self::DATA_MAX_BYTES);
        $cols->string("raw")->length(255)->nullable();
        $cols->int("time_stamp")->bytes(4)->unSigned();
    }

    /**
     * @param string $key
     * @param Buffer $data
     * @param string|null $raw
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public static function Save(string $key, Buffer $data, ?string $raw = null): void
    {
        if (!Validator::isASCII($key, "-.+:@")) {
            throw new \InvalidArgumentException('Invalid DataStore object key');
        } elseif ($data->len() > self::DATA_MAX_BYTES) {
            throw new \LengthException(
                sprintf('Length of DataStore object "%s" cannot exceed %d bytes', $key, self::DATA_MAX_BYTES)
            );
        }

        $aK = AppKernel::getInstance();
        $query = 'INSERT ' . 'INTO `%s` (`key`, `data`, `raw`, `time_stamp`) VALUES (:key, :data, :raw, :timeStamp) ' .
            'ON DUPLICATE KEY UPDATE `data`=:data, `raw`=:raw, `time_stamp`=:timeStamp';
        $queryData = [
            "key" => $key,
            "data" => $data->raw(),
            "raw" => $raw,
            "timeStamp" => time()
        ];

        $aK->db->primary()->exec(sprintf($query, self::NAME), $queryData);
    }
}
