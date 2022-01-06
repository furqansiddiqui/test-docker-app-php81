<?php
declare(strict_types=1);

namespace bin;


/**
 * Class deploy_db
 * @package bin
 */
class deploy_db extends abstract_db_builder_script
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     * @throws \Comely\Database\Exception\SchemaTableException
     */
    public function exec(): void
    {
        $this->createDbTables();
    }
}
