<?php

declare(strict_types=1);

namespace BasePHP\Database;

use Closure;
use DateTimeInterface;
use PDO;
use PDOStatement;

class Connection
{
    private ?PDO $pdo;
    private string $dateFormat = 'Y-m-d H:i:s';
    private int $fetchMode = PDO::FETCH_OBJ;
    private int $transactionCount = 0;

    public function __construct(PDO|Closure|null $pdo)
    {
        $this->setPdo($pdo);
    }

    /**
     * @param PDO|Closure|null $pdo
     * @return void
     */
    private function setPdo(PDO|Closure|null $pdo): void
    {
        if ($pdo instanceof Closure) {
            $this->pdo = $pdo();
        } else {
            $this->pdo = $pdo;
        }
    }

    /**
     * @param string $query
     * @param array $params
     * @return PDOStatement
     */
    public function execute(string $query, array $params = []): PDOStatement
    {
        $statement = $this->prepareStatement($query, $params);

        $statement->execute();

        return $statement;
    }

    public function prepareStatement(string $query, array $params = []): PDOStatement
    {
        $statement = $this->getPdo()->prepare($query);

        $this->bindValues($statement, $params);

        $statement->setFetchMode($this->fetchMode);

        return $statement;
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param PDOStatement $statement
     * @param array $bindings
     * @return void
     */
    private function bindValues(PDOStatement $statement, array $bindings = []): void
    {
        foreach ($bindings as $key => $value) {
            $param = is_string($key) ? $key : $key + 1;

            if ($value instanceof DateTimeInterface) {
                $value = $value->format($this->dateFormat);
            }

            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_resource($value) => PDO::PARAM_LOB,
                default => PDO::PARAM_STR
            };

            $statement->bindValue($param, $value, $type);
        }
    }

    /**
     * @return void
     */
    public function beginTransaction(): void
    {
        if ($this->transactionCount === 0) {
            $this->getPdo()->beginTransaction();
        } elseif ($this->transactionCount >= 1) {
            $savePointName = 'transaction' . ($this->transactionCount + 1);
            $this->getPdo()->exec("SAVEPOINT $savePointName");
        }

        $this->transactionCount++;
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        if ($this->transactionCount === 1) {
            $this->getPdo()->commit();
        }

        $this->transactionCount = max(0, $this->transactionCount - 1);
    }

    /**
     * Rolls back a transaction to the specified level.
     *
     * If the level is not specified, the transaction will be rolled back to the
     * previous level. If the level is 0, the transaction will be rolled back
     * completely.
     *
     * @param int|null $toLevel The level to roll back to.
     * @return void
     */
    public function rollback(int $toLevel = null): void
    {
        $toLevel = is_null($toLevel) ? $this->transactionCount - 1 : $toLevel;

        if ($toLevel < 0 || $toLevel >= $this->transactionCount) {
            return;
        }

        // Roll back the transaction completely if the level to rollback is 0.
        if ($toLevel === 0) {
            $pdo = $this->getPdo();

            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
        } else {
            // Roll back the transaction to the state after the save point at the specified level.
            // Each save point saves the state before subsequent operations.
            // For instance, rolling back to save point 2 will revert to the state before save point 2 and after save point 1.
            // To roll back to the save point at level $x, roll back to the save point at level $x+1.
            $savePointName = 'transaction' . ($toLevel + 1);
            $this->getPdo()->exec("ROLLBACK TO SAVEPOINT $savePointName");
        }

        $this->transactionCount = $toLevel;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @return void
     */
    private function disconnect(): void
    {
        $this->setPdo(null);
    }

    public function select(string $table, array|string $columns = ['*']): array
    {
        $select = is_array($columns) ? implode(', ', $columns) : $columns;
        $query = "SELECT $select FROM $table";

        return $this->execute($query)->fetchAll();
    }
}