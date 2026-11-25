<?php

namespace App\Database\Connectors;

use Illuminate\Database\Connectors\SqlServerConnector as LaravelSqlServerConnector;

class PinnedSqlServerConnector extends LaravelSqlServerConnector
{
    protected function getSqlSrvDsn(array $config)
    {
        $dsn = parent::getSqlSrvDsn($config);
        $driver = $config['odbc_driver'] ?? null;

        if (! is_string($driver) || trim($driver) === '') {
            return $dsn;
        }

        $driver = str_replace('}', '}}', trim($driver));

        return preg_replace('/^sqlsrv:/', 'sqlsrv:Driver={'.$driver.'};', $dsn, 1);
    }
}
