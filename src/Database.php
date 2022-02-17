<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem;

use Exception;
use LogicException;
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
 * @version 1.2.0
 */

/**
 * Class to interact with the database
 */
class Database
{

    /**
     * @var array<string,string|int>
     *
     * @phpstan-var array{
     *  adapter: string,
     *  name: string,
     *  host: string,
     *  user: string,
     *  pass: string,
     *  port: int,
     *  charset: int
     * }
     */
    private $dbConfig = null;

    /**
     * @var PDO|null
     */
    private $connection = null;

    /** @var self[] $instances */
    protected static $instances = [];

    public const MAIN_CONNECTION   = 0x1;
    public const SECOND_CONNECTION = 0x2;
    public const THIRD_CONNECTION  = 0x2;

    /**
     * Build a Database object
     *
     * @param array|null $config The config block or null if you will use the setConnection method
     * @param int $connection Database::MAIN_CONNECTION, Database::SECOND_CONNECTION, Database::THIRD_CONNECTION
     * @phpstan-param array{
     *  currentDatabaseEnv: string,
     *  database: array<string, array{
     *      adapter: string,
     *      name: string,
     *      host: string,
     *      user: string,
     *      pass: string,
     *      port: int,
     *      charset: int
     *  }>
     * }|null $config
     * @phpstan-param Database::*_CONNECTION $connection
     */
    public function __construct(?array $config, int $connection = self::MAIN_CONNECTION)
    {
        /** @var array{currentDatabaseEnv?: string, database?: array<string, array{adapter: string, name: string, host: string, user: string, pass: string, port: int, charset: int}>}|null $config */
        if ((! isset($config['database']) || ! isset($config['currentDatabaseEnv'])) && $config !== null) {
            throw new Exception('Invalid config to create the Database');
        }

        if ($config !== null) {
            $this->dbConfig = $config['database'][$config['currentDatabaseEnv']];
        }
        self::$instances[$connection] = $this;
    }

    /**
     * @since 1.1.0
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection = null;
        }
    }

    /**
     * @since 1.1.0
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Connect to the database
     *
     * @throws PDOException when the connetion fails
     */
    public function connect(): void
    {
        if ($this->dbConfig === null) {
            throw new LogicException('You need to pass the config when creating the Database instance to use the connect method.');
        }

        $dsn = sprintf(
            '%s:dbname=%s;host=%s;port=%d;charset=%s',
            $this->dbConfig['adapter'],
            $this->dbConfig['name'],
            $this->dbConfig['host'],
            $this->dbConfig['port'],
            $this->dbConfig['charset']
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

    /**
     * @since 1.2.0
     */
    public function setConnection(PDO $connection): void
    {
        $this->connection = $connection;
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            throw new Exception('The database is not connected, please call ->connect()');
        }

        return $this->connection;
    }

    /**
     * Access the database instance
     *
     * @param int $connection Database::MAIN_CONNECTION, Database::SECOND_CONNECTION, Database::THIRD_CONNECTION
     */
    public static function getInstance(int $connection = self::MAIN_CONNECTION): self
    {
        if ((self::$instances[$connection] ?? null) === null) {
            throw new Exception(
                sprintf(
                    'The Database object was never created (connection: %d), use new Database() at least once',
                    $connection
                )
            );
        }

        return self::$instances[$connection];
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
