<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, version 2.0.
 * If a copy of the MPL was not distributed with this file,
 * You can obtain one at https://mozilla.org/MPL/2.0/.
 * @license MPL-2.0 https://mozilla.org/MPL/2.0/
 * @source https://github.com/wdes/simple-php-model-system
 */

/**
 * Class to interact with the database
 */
class Database
{

    /**
     * @var array
     */
    private $dbConfig;

    /**
     * @var PDO
     */
    private $connection;

    /** @var self|null $instance */
    protected static $instance = null;

    public function __construct(array $config)
    {
        $this->dbConfig = $config['database'][$config['currentDatabaseEnv']];
        self::$instance = $this;
    }

    /**
     * @throws PDOException when the connetion fails
     */
    public function connect(): void
    {
        $dsn = sprintf(
            '%s:dbname=%s;host=%s;port=%d;charset=%s',
            $this->dbConfig['adapter'],
            $this->dbConfig['name'],
            $this->dbConfig['host'],
            $this->dbConfig['port'],
            $this->dbConfig['charset'],
        );
        try {
            $this->connection = new PDO(
                $dsn,
                $this->dbConfig['user'],
                $this->dbConfig['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
        } catch (PDOException $pe) {
            throw $pe;
        }
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            throw new Exception('The database is not connected, please call ->connect()');
        }

        return $this->connection;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new Exception('The Database object was never created, use new Database() at least once');
        }

        return self::$instance;
    }

    /**
     * @param string $query The SQL query
     * @param array<int|string,mixed> $exe PDO input params
     */
    public function query(string $query, array $exe = []): ?PDOStatement
    {
        if ($this->connection === null) {
            throw new Exception('The database is not connected, please call ->connect()');
        }

        try {
            $sth = $this->connection->prepare($query);
            $sth->execute($exe);
            return $sth;
        } catch (PDOException $e) {
            throw $e;
        }
    }

}
