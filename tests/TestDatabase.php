<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem\Test;

use PDOStatement;
use SimplePhpModelSystem\Database;

class TestDatabase extends Database
{
    /**
     * @var array[]
     */
    private static $queries = [];

    /**
     * @return array[]
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    public static function setQuery(string $query, array $params): void
    {
        self::$queries[] = [
            $query,
            $params,
        ];
    }

    public static function emptyQueries(): void
    {
        self::$queries = [];
    }

    public function query(string $query, array $exe = []): ?PDOStatement
    {
        self::setQuery($query, $exe);
        return parent::query($query, $exe);
    }

}
