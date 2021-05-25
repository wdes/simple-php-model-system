<?php

declare(strict_types = 1);

namespace SimplePhpModelSystem;

use Exception;
use LogicException;
use PDO;

/**
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, version 2.0.
 * If a copy of the MPL was not distributed with this file,
 * You can obtain one at https://mozilla.org/MPL/2.0/.
 * @license MPL-2.0 https://mozilla.org/MPL/2.0/
 * @source https://github.com/wdes/simple-php-model-system
 * @version 1.1.0
 */

abstract class AbstractModel
{
    /**
     * @var array<string,mixed>
     */
    protected $data;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string|string[]
     */
    protected $primaryKey = 'id';

    /**
     * @var array<string,true>
     */
    private $keysToModify = [];

    final public function __construct()
    {
        // Must not be override
    }

    /**
     * The keys of the data
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * The values of the data
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
     * @param string|int $primaryKeyValues
     * @return static|null
     */
    public static function findById(...$primaryKeyValues): ?self
    {
        $instance = new static();
        $pkClause = $instance->getPrimaryKeyClause();
        $query    = 'SELECT * FROM `' . $instance->getTable() . '` WHERE ' . $pkClause[0] . ';';

        return self::buildOneFromQuery($query, $primaryKeyValues);
    }

    /**
     * @return static[]
     */
    public static function buildMultipleFromQuery(string $query, array $valuesToBind): array
    {
        $statement = Database::getInstance()->query($query, $valuesToBind);
        if ($statement === null) {
            return [];
        }
        $data = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $newRow = new static();
            $row    = $newRow->transform($row);
            $newRow->setData($row);
            $data[] = $newRow;
        }

        return $data;
    }

    /**
     * @return static|null Null if not found
     */
    public static function buildOneFromQuery(string $query, array $valuesToBind): ?self
    {
        $statement = Database::getInstance()->query($query, $valuesToBind);
        if ($statement === null) {
            return null;
        }

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
     * @return static|null
     */
    public static function findWhere(array $whereClause): ?self
    {
        $instance = new static();

        $whereClauseString = '';
        $valuesToBind      = [];

        $whereKeys = array_keys($whereClause);
        $last      = end($whereKeys);
        foreach ($whereClause as $key => $valueToBind) {
            $notLast            = $key !== $last;
            $whereClauseString .= '`' . $key . '` = ?';
            if ($notLast) {
                $whereClauseString .= ' AND ';
            }
            $valuesToBind[] = $valueToBind;
        }

        $query = 'SELECT * FROM `' . $instance->getTable() . '` WHERE ' . $whereClauseString . ';';

        return self::buildOneFromQuery($query, $valuesToBind);
    }

    public function update(): bool
    {
        if (count($this->keysToModify) === 0) {
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

        $whereClauseString = '';
        $valuesToBind      = [];

        $whereKeys = array_keys($whereClause);
        $last      = end($whereKeys);
        foreach ($whereClause as $key => $valueToBind) {
            $notLast  = $key !== $last;
            $operator = '= ?';

            if (is_array($valueToBind)) {
                $operator = 'IN(' . join(',', array_fill(0, count($valueToBind), '?')) . ')';
            }

            $whereClauseString .= '`' . $key . '` ' . $operator;
            if ($notLast) {
                $whereClauseString .= ' AND ';
            }
            if (is_array($valueToBind)) {
                array_push($valuesToBind, ...$valueToBind);
                continue;
            }
            $valuesToBind[] = $valueToBind;
        }

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
     * @return string|int
     */
    public function getKey()
    {
        if (is_array($this->primaryKey)) {
            throw new Exception('The primary key is multiple, you can do use this function');
        }

        return $this->data[$this->primaryKey];
    }

    /**
     * setData
     *
     * @param  array<string,mixed> $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * setData
     *
     * @param  mixed $key
     * @param  string|int $value
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
     * @param array<string,string|int> $data
     * @return array<string,string|int>
     */
    protected function transform(array $data): array
    {
        return $data;
    }

    /**
     * @return array<string,string|int>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function refresh(): bool
    {
        $pkClause = $this->getPrimaryKeyClause();

        $query     = 'SELECT * FROM `' . $this->getTable() . '` WHERE ' . $pkClause[0] . ';';
        $statement = Database::getInstance()->query($query, $pkClause[1]);
        if ($statement === null) {
            return false;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        $row = $this->transform($row);
        $this->setData($row);

        return true;
    }

    public function getPrimaryKeyClause(): array
    {
        $pks = $this->primaryKey;
        if (is_string($pks)) {
            $pks = [$pks];
        }
        $values   = [];
        $pkString = '';
        foreach ($pks as $pk) {
            $pkString .= '`' . $pk . '` = ?';
            $values[]  = $this->data[$pk] ?? null;
        }

        return [$pkString, $values];
    }

}
