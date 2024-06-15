<?php

declare(strict_types=1);

namespace App\Persistence;

use PDO;

/**
 *
 */
class PdoFactory
{
    private string $driver;
    private string $host;
    private int $port;
    private string $dbname;
    private string $user;
    private string $password;

    public function __construct(string $driver, string $host, int $port, string $dbname, string $user, string $password)
    {
        $this->driver = 'pgsql';
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;
    }

    public function createPDO(): PDO
    {
        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s', $this->driver, $this->host, $this->port, $this->dbname);

        return new PDO($dsn, $this->user, $this->password);
    }
}
