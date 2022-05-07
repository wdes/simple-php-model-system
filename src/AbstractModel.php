<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem;

use Exception;
use Generator;
use LogicException;
use PDO;

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, version 2.0.
 * If a copy of the MPL was not distributed with this file,
 * You can obtain one at https://mozilla.org/MPL/2.0/.
 * @license MPL-2.0 https://mozilla.org/MPL/2.0/
 * @source https://github.com/wdes/simple-php-model-system
 * @version 1.3.0
 */

abstract class AbstractModel
{
    /**
     * @var array<string|int,mixed>
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string|string[]
     */
    protected $primaryKey = 'id';

    /**
     * @var array<string|int,true>
     */
    private $keysToModify = [];

    final public function __construct()
    {
        // Must not be override
    }

    /**
     * The keys of the data
     *
     * @return (string|int)[]
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * The values of the data
     *
     * @return mixed[]
     */
    public function getValues(): array
    {
        return array_values($this->data);
    }

    /**
     * The values of the data for the key
     *
     * @return mixed|null
     */
    public function getValue(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @since 1.1.0
     */
    public function hasChanges(): bool
    {
        return count($this->keysToModify) > 0;
    }

    /**
     * @since 1.2.0
     *
     * @return (string|int)[]
     */
    public function getChangedKeys(): array
    {
        return array_keys($this->keysToModify);
    }

    public function save(): bool
    {
        $keys               = implode(', ', $this->getKeys());
        $values             = array_values($this->data);
        $valuesPlaceholders = implode(', ', array_fill(0, count($values), '?'));
        $query              = 'INSERT INTO `' . $this->table . '` (' . $keys . ')'
                            . ' VALUES (' . $valuesPlaceholders . ');';
        $statement          = Database::getInstance()->query($query, $values);
        if ($statement === null) {
            return false;
        }

        $insertedId = Database::getInstance()->getConnection()->lastInsertId();

        // '0' is the value that is returned if the Id could not be given
        if ($insertedId !== '0' && is_string($this->primaryKey) && !isset($this->data[$this->primaryKey])) {
            $this->data[$this->primaryKey] = $insertedId;
        }

        return $statement->rowCount() === 1;
    }

    public static function count(): ?int
    {
        $instance = new static();

        $count = Database::getInstance()->getConnection()->query(
            'SELECT COUNT(*) FROM `' . $instance->getTable() . '`'
        );

        if ($count === false) {
            return null;
        }

        $rows = $count->fetchColumn();

        if ($rows === false) {
            return null;
        }

        return (int) $rows;
    }

    /**
     * @param static[] $instances
     */
    public static function saveBatch(array $instances): bool
    {
        if ($instances === []) {
            return true;
        }

        $keyNames           = $instances[0]->getKeys();
        $keys               = implode(', ', $keyNames);
        $valuesPlaceholders = implode(', ', array_fill(0, count($keyNames), '?'));
        $query              = 'INSERT INTO `' . $instances[0]->getTable() . '` (' . $keys . ') VALUES';
        $values             = [];

        $instanceKeys = array_keys($instances);
        $last         = end($instanceKeys);// Only variables should be passed by reference
        foreach ($instances as $key => $instance) {
            $query .= ' (' . $valuesPlaceholders . ')';
            if ($key !== $last) {
                $query .= ',';
            }
            array_push($values, ...$instance->getValues());
        }

        $statement = Database::getInstance()->query($query, $values);
        if ($statement === null) {
            return false;
        }

        return $statement->rowCount() === count($instances);
    }

    /**
     * fetchAll
     * @param array<string,string> $order 1 = column, 2 = DESC/ASC
     * @return static[]
     */
    public static function fetchAll(array $order = []): array
    {
        $orderBy  = self::orderParam($order);
        $instance = new static();
        $query    = 'SELECT * FROM `' . $instance->getTable() . '`' . $orderBy . ';';
        return self::buildMultipleFromQuery($query, []);
    }

    /**
     * @param array<string,string> $order 1 = column, 2 = DESC/ASC
     */
    private static function orderParam(array $order): string
    {
        if (count($order) === 0) {
            return '';
        }

        $query = [];

        foreach ($order as $key => $value) {
            $query[] = '`' . $key . '` ' . $value;
        }

        return ' ORDER BY ' . implode(', ', $query);
    }

    /**
     * @param string|int $primaryKeyValue
     * @return static|null
     */
    public static function findById($primaryKeyValue): ?self
    {
        $instance = new static();
        if (is_array($instance->primaryKey)) {
            throw new LogicException(
                'This model has multiple columns as a key, you can not use findById. Use findWhere instead.'
            );
        }

        return self::findWhere(
            [
                $instance->primaryKey => $primaryKeyValue,
            ]
        );
    }

    /**
     * @param array<int|string,mixed> $valuesToBind
     * @return static[]
     */
    public static function buildMultipleFromQuery(string $query, array $valuesToBind): array
    {
        $data = [];
        $rows = self::generateMultipleFromQuery($query, $valuesToBind);
        foreach ($rows as $row) {
            $data[] = $row;
        }
        return $data;
    }

    /**
     * @param array<int|string,mixed> $valuesToBind
     * @return \Generator<int,static>
     */
    public static function generateMultipleFromQuery(string $query, array $valuesToBind): Generator
    {
        $statement = Database::getInstance()->query($query, $valuesToBind);
        if ($statement === null) {
            return;
        }

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $newRow = new static();
            /** @var array<string|int,mixed> $row */
            $row = $row;// phpstan workaround for type doc
            $row = $newRow->transform($row);
            $newRow->setData($row);
            yield $newRow;
        }
    }

    /**
     * @return static|null Null if not found
     * @param array<int|string,mixed> $valuesToBind PDO input params
     */
    public static function buildOneFromQuery(string $query, array $valuesToBind): ?self
    {
        $statement = Database::getInstance()->query($query, $valuesToBind);
        if ($statement === null) {
            return null;
        }

        /** @var array<string|int,mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $newRow = new static();
        $row    = $newRow->transform($row);
        $newRow->setData($row);

        return $newRow;
    }

    /**
     * @param array<string,mixed> $whereClause
     * @param bool $asGenerator Return a generator
     * @return static[]|\Generator<int,static>
     * @phpstan-return ($asGenerator is true ? \Generator<int,static> : static[])
     *
     * @since 1.3.0
     */
    public static function collectCursorOrArrayWhere(array $whereClause, bool $asGenerator)
    {
        $instance = new static();

        [$whereClauseString, $valuesToBind] = self::buildWhereClause($whereClause);

        $query = 'SELECT * FROM `' . $instance->getTable() . '` WHERE ' . $whereClauseString . ';';

        if ($asGenerator) {
            return self::generateMultipleFromQuery($query, $valuesToBind);
        }

        return self::buildMultipleFromQuery($query, $valuesToBind);
    }

    /**
     * @param array<string,mixed> $whereClause
     * @return static[]
     *
     * @since 1.3.0
     */
    public static function collectWhere(array $whereClause): array
    {
        return self::collectCursorOrArrayWhere($whereClause, false);
    }

    /**
     * @param array<string,mixed> $whereClause
     * @return \Generator<int,static>
     *
     * @since 1.3.0
     */
    public static function collectCursorWhere(array $whereClause): Generator
    {
        return self::collectCursorOrArrayWhere($whereClause, true);
    }

    /**
     * @param array<string,mixed> $whereClause
     * @return array<int,string|mixed[]>
     *
     * @phpstan-return array{0: string, 1: mixed[]}
     *
     * @since 1.3.0
     */
    public static function buildWhereClause(array $whereClause): array
    {
        $whereClauseString = '';
        $valuesToBind      = [];

        $whereKeys = array_keys($whereClause);
        $last      = end($whereKeys);
        foreach ($whereClause as $key => $valueToBind) {
            $notLast  = $key !== $last;
            $operator = '= ?';
            $hasBind  = true;

            if ($valueToBind === null) {
                $operator = 'IS NULL';
                $hasBind  = false;
            }

            $multipleValuesMode = $valueToBind !== null && is_array($valueToBind);

            if ($multipleValuesMode) {
                $operator = 'IN(' . join(',', array_fill(0, count($valueToBind), '?')) . ')';
            }

            $whereClauseString .= '`' . $key . '` ' . $operator;
            if ($notLast) {
                $whereClauseString .= ' AND ';
            }
            if ($multipleValuesMode) {
                array_push($valuesToBind, ...$valueToBind);
                continue;
            }
            if ($hasBind) {
                $valuesToBind[] = $valueToBind;
            }
        }

        return [$whereClauseString, $valuesToBind];
    }

    /**
     * @param array<string,mixed> $whereClause
     * @return static|null
     */
    public static function findWhere(array $whereClause): ?self
    {
        $instance = new static();

        [$whereClauseString, $valuesToBind] = self::buildWhereClause($whereClause);

        $query = 'SELECT * FROM `' . $instance->getTable() . '` WHERE ' . $whereClauseString . ' LIMIT 1;';

        return self::buildOneFromQuery($query, $valuesToBind);
    }

    public function update(): bool
    {
        if (! $this->hasChanges()) {
            return false;
        }

        $pkClause = $this->getPrimaryKeyClause();

        $values             = [];
        $valuesPlaceholders = [];

        foreach ($this->keysToModify as $key => $_) {
            $valuesPlaceholders[] = '`' . $key . '` = ?';
            $values[]             = $this->data[$key];
        }

        array_push($values, ...$pkClause[1]);
        $query     = 'UPDATE `' . $this->table . '` SET '
                    . implode(', ', $valuesPlaceholders) . ' WHERE ' . $pkClause[0] . ';';
        $statement = Database::getInstance()->query($query, $values);

        if ($statement === null) {
            return false;
        }

        if ($statement->rowCount() === 1) {
            $this->keysToModify = [];
            return true;
        }

        return false;
    }

    /**
     * @param string|int|float|bool|null $pkValue The value of the primary key
     */
    public static function deleteWherePrimary($pkValue): bool
    {
        $instance = new static();

        if (is_array($instance->primaryKey)) {
            throw new LogicException(
                'Use deleteWhere to delete this model.'
            );
        }

        return self::deleteWhere(
            [
            $instance->primaryKey => $pkValue,
            ]
        );
    }

    /**
     * @param array<string,mixed> $whereClause
     */
    public static function deleteWhere(array $whereClause): bool
    {
        $instance = new static();

        [$whereClauseString, $valuesToBind] = self::buildWhereClause($whereClause);

        $query     = 'DELETE FROM `' . $instance->getTable() . '` WHERE ' . $whereClauseString . ';';
        $statement = Database::getInstance()->query($query, $valuesToBind);
        if ($statement === null) {
            return false;
        }
        return $statement->errorCode() === '00000';
    }

    public static function deleteAll(): bool
    {
        $instance  = new static();
        $query     = 'DELETE FROM `' . $instance->getTable() . '`;';
        $statement = Database::getInstance()->query($query);
        if ($statement === null) {
            return false;
        }
        return $statement->errorCode() === '00000';
    }

    public function delete(): bool
    {
        $pkClause  = $this->getPrimaryKeyClause();
        $query     = 'DELETE FROM `' . $this->getTable() . '` WHERE ' . $pkClause[0] . ';';
        $statement = Database::getInstance()->query($query, $pkClause[1]);
        if ($statement === null) {
            return false;
        }

        return $statement->errorCode() === '00000';
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        if (is_array($this->primaryKey)) {
            throw new Exception('The primary key is multiple, you can do use this function');
        }

        return $this->data[$this->primaryKey];
    }

    /**
     * Set the data
     *
     * @param  array<string|int,mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Merge data and affect changed states
     *
     * @param  array<string|int,mixed> $data
     * @return void
     */
    public function mergeData(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Set the data in the model
     *
     * @param  string|int $key
     * @param  mixed $value
     * @return void
     */
    protected function set($key, $value): void
    {
        if ($this->data[$key] === $value) {
            return;// No changes to apply
        }

        $this->data[$key]         = $value;
        $this->keysToModify[$key] = true;
    }

    /**
     * @param array<string|int,mixed> $data
     * @return array<string|int,mixed>
     */
    protected function transform(array $data): array
    {
        return $data;
    }

    /**
     * @return array<string|int,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function refresh(): bool
    {
        $pkClause = $this->getPrimaryKeyClause();

        $query     = 'SELECT * FROM `' . $this->getTable() . '` WHERE ' . $pkClause[0] . ' LIMIT 1;';
        $statement = Database::getInstance()->query($query, $pkClause[1]);
        if ($statement === null) {
            return false;
        }

        /** @var array<string|int,mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $row = $this->transform($row);
        $this->setData($row);

        return true;
    }

    /**
     * @return array<int,string|mixed[]>
     * @phpstan-return array{0: string, 1: mixed[]}
     */
    public function getPrimaryKeyClause(): array
    {
        $keyNames = $this->primaryKey;
        if (! is_array($keyNames)) {
            $keyNames = [$keyNames];
        }
        $dataWhere = [];
        foreach ($keyNames as $keyName) {
            if (array_key_exists($keyName, $this->data)) {
                $dataWhere[$keyName] = $this->data[$keyName];
            }
        }

        return self::buildWhereClause($dataWhere);
    }

}
