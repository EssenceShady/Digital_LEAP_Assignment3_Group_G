<?php
declare(strict_types=1);

namespace Leap\Core;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = $GLOBALS['config']['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) $cfg['port'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = [])
    {
        $value = self::q($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            $placeholders
        );
        self::q($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($data)));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, $sets, $where);
        return self::q($sql, array_merge(array_values($data), $params))->rowCount();
    }

    public static function tx(callable $work)
    {
        if (self::pdo()->inTransaction()) {
            return $work();
        }
        self::pdo()->beginTransaction();
        try {
            $result = $work();
            self::pdo()->commit();
            return $result;
        } catch (Throwable $e) {
            self::pdo()->rollBack();
            throw $e;
        }
    }
}
