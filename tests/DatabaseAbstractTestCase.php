<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem\Tests;

use PDO;
use SimplePhpModelSystem\Test\TestDatabase;

abstract class DatabaseAbstractTestCase extends AbstractTestCase
{

    /**
     * @var TestDatabase
     */
    private $dbConnection = null;

    public function setUp(): void
    {
        $this->loadDatabase();
    }

    /**
     * assertWasQuery
     *
     * @param  string $queryToCheck
     * @param  array<int|string,mixed>|null $execToCheck
     * @return bool
     */
    protected function assertWasQuery(string $queryToCheck, ?array $execToCheck = null): bool
    {
        $queries      = TestDatabase::getQueries();
        $queriesFound = [];
        foreach ($queries as $query) {
            if ($query[0] === $queryToCheck) {
                $this->assertStringContainsString($queryToCheck, $query[0]);
                if ($execToCheck !== null) {
                    $this->assertSame($execToCheck, $query[1]);
                }
                return true;
            }
            $queriesFound[] = $query[0];
        }

        $this->assertFalse(
            true,
            sprintf(
                'Query "%s" not found, in a total of ' . count($queries) . ' queries: ' . implode(', ', $queriesFound),
                $queryToCheck
            )
        );
        return false;
    }

    protected function loadDatabase(): void
    {
        if ($this->dbConnection !== null) {
            return;
        }

        $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
        if (! file_exists($configFile)) {
            echo 'Missing config file' . PHP_EOL;
            printf('Please copy the default config file to %s' . PHP_EOL, $configFile);
            exit(1);
        }
        $config             = require $configFile;
        $this->dbConnection = new TestDatabase($config);
        $this->dbConnection->connect();
        $tables = $this->dbConnection->query('SHOW TABLES;');
        if ($tables === null) {
            'Unable to list tables' . PHP_EOL;
            exit(1);
        }
        $tablesList = $tables->fetchAll(PDO::FETCH_COLUMN);
        if ($tablesList === false) {
            'Unable to fetch table list value' . PHP_EOL;
            exit(1);
        }
        if (in_array('users', $tablesList)) {
            $this->emptyQueries();
            return;
        }
        $this->dbConnection->query(
            <<<'SQL'
            CREATE TABLE `users` (
                user_uuid VARCHAR(128),
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                date_of_birth DATE
            ) CHARACTER SET 'utf8';
        SQL
        );
        $this->emptyQueries();
    }

    protected function emptyQueries(): void
    {
        TestDatabase::emptyQueries();
    }

}
