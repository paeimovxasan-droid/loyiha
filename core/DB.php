<?php
declare(strict_types=1);

namespace Tortinmang\Core;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;

final class DB
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $cfg = Config::get('db');
        $host    = $cfg['host'] ?? 'localhost';
        $dbname  = $cfg['name'] ?? '';
        $charset = $cfg['charset'] ?? 'utf8mb4';
        $user    = $cfg['user'] ?? '';
        $pass    = $cfg['pass'] ?? '';
        $port    = $cfg['port'] ?? null; // optional
        $socket  = $cfg['socket'] ?? null; // optional

        $attempts = [];

        // Primary: host (TCP) with optional port
        $hosts = [$host];
        if ($host === '127.0.0.1') $hosts[] = 'localhost';
        if ($host === 'localhost') $hosts[] = '127.0.0.1';

        foreach ($hosts as $h) {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $h, $dbname, $charset);
            if ($port) $dsn .= ";port={$port}";
            $attempts[] = ['type' => 'tcp', 'dsn' => $dsn, 'host' => $h];
        }

        // If socket path provided in config, try unix_socket
        if (!empty($socket)) {
            $attempts[] = ['type' => 'socket', 'dsn' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $dbname, $charset), 'socket' => $socket];
        }

        // Common socket locations to try (if available on this machine)
        $commonSockets = ['/var/run/mysqld/mysqld.sock', '/run/mysqld/mysqld.sock', '/tmp/mysql.sock', '/var/lib/mysql/mysql.sock'];
        foreach ($commonSockets as $s) {
            if (file_exists($s)) {
                $attempts[] = ['type' => 'socket', 'dsn' => sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $s, $dbname, $charset), 'socket' => $s];
            }
        }

        $lastException = null;
        foreach ($attempts as $a) {
            try {
                $opts = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ];
                $this->pdo = new PDO($a['dsn'], $user, $pass, $opts);
                Logger::info('DB connected', ['method' => $a['type'], 'dsn_preview' => substr($a['dsn'], 0, 120)]);
                $lastException = null;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
                Logger::warning('DB connect attempt failed', ['dsn' => $a['dsn'] ?? null, 'err' => $e->getMessage()]);
                // If access denied for specific host, try next attempt (e.g., 127.0.0.1 vs localhost)
                continue;
            }
        }

        if ($lastException !== null) {
            // Provide more helpful logging for common errors
            $msg = $lastException->getMessage();
            if (str_contains($msg, 'Access denied')) {
                Logger::error('DB connection failed: access denied. Suggest checking MySQL user host permissions (user@host).', ['err' => $msg]);
            } else {
                Logger::error('DB connection failed: ' . $msg);
            }
            throw new RuntimeException('Database connection error');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Prevent cloning */
    private function __clone(): void {}

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute query and fetch single row.
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function fetch(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $columns      = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $this->query(
            "INSERT INTO `{$table}` ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(
            fn(string $col): string => "`{$col}` = ?",
            array_keys($data)
        );
        $setClause = implode(', ', $setParts);

        $stmt = $this->query(
            "UPDATE `{$table}` SET {$setClause} WHERE {$where}",
            [...array_values($data), ...$whereParams]
        );

        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(
            "DELETE FROM `{$table}` WHERE {$where}",
            $params
        )->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /** Shortcut: get DB instance */
    public static function get(): self
    {
        return self::getInstance();
    }
}
