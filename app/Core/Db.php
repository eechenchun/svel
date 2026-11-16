<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Db.php
 * @desc 数据库层：MySQL 与 SQLite 双驱动，业务代码不写方言
 */

namespace Svel\Core;

use PDO;

final class Db
{
    private static ?self $inst = null;

    private PDO $pdo;
    private string $driver;
    private string $prefix;
    private int $depth = 0;

    private function __construct(array $cfg)
    {
        $this->driver = strtolower((string)($cfg['driver'] ?? 'mysql'));
        $this->prefix = (string)($cfg['prefix'] ?? 'svel_');

        if (!preg_match('/^[A-Za-z0-9_]*$/', $this->prefix)) {
            throw new \RuntimeException('表前缀只允许字母、数字与下划线');
        }

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($this->driver === 'sqlite') {
            $file = (string)($cfg['file'] ?? '');
            if ($file === '') {
                throw new \RuntimeException('SQLite 模式必须在 config.php 中指定 db.file');
            }
            $this->pdo = new PDO('sqlite:' . $file, null, null, $opts);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
            return;
        }

        if ($this->driver !== 'mysql') {
            throw new \RuntimeException('不支持的数据库驱动：' . $this->driver);
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string)($cfg['host'] ?? '127.0.0.1'),
            (int)($cfg['port'] ?? 3306),
            (string)($cfg['database'] ?? ''),
            (string)($cfg['charset'] ?? 'utf8mb4')
        );
        $this->pdo = new PDO($dsn, (string)($cfg['username'] ?? ''), (string)($cfg['password'] ?? ''), $opts);
    }

    public static function boot(array $cfg): void
    {
        self::$inst = new self($cfg);
    }

    public static function inst(): self
    {
        if (self::$inst === null) {
            throw new \RuntimeException('数据库尚未初始化');
        }
        return self::$inst;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /** 真实表名（带前缀） */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** 标识符引用，两种库引号不同 */
    public function qi(string $name): string
    {
        $q = $this->driver === 'sqlite' ? '"' : '`';
        return $q . str_replace($q, '', $name) . $q;
    }

    public function q(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->q($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function val(string $sql, array $params = [], mixed $default = null): mixed
    {
        $v = $this->q($sql, $params)->fetchColumn();
        return $v === false ? $default : $v;
    }

    public function exec(string $sql, array $params = []): int
    {
        return $this->q($sql, $params)->rowCount();
    }

    /** 插入并返回自增主键 */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $this->qi($this->table($table))
            . ' (' . implode(',', array_map(fn(string $c): string => $this->qi($c), $cols)) . ')'
            . ' VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->q($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = [];
        foreach (array_keys($data) as $c) {
            $set[] = $this->qi($c) . ' = ?';
        }
        $sql = 'UPDATE ' . $this->qi($this->table($table)) . ' SET ' . implode(',', $set) . ' WHERE ' . $where;
        return $this->exec($sql, array_merge(array_values($data), $params));
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->exec('DELETE FROM ' . $this->qi($this->table($table)) . ' WHERE ' . $where, $params);
    }

    public function begin(): void
    {
        if ($this->depth === 0) {
            if ($this->driver === 'sqlite') {
                $this->pdo->exec('BEGIN');
            } else {
                $this->pdo->beginTransaction();
            }
        }
        $this->depth++;
    }

    public function commit(): void
    {
        $this->depth = max(0, $this->depth - 1);
        if ($this->depth > 0) {
            return;
        }
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('COMMIT');
        } else {
            $this->pdo->commit();
        }
    }

    public function rollback(): void
    {
        $this->depth = max(0, $this->depth - 1);
        if ($this->depth > 0) {
            return;
        }
        if ($this->driver === 'sqlite') {
            $this->pdo->exec('ROLLBACK');
        } else {
            $this->pdo->rollBack();
        }
    }

    /**
     * 逻辑类型 → 物理类型。安装器建表与升级共用，避免两处各写一套。
     */
    public static function type(string $logical, string $driver): string
    {
        $map = [
            'pk'         => ['mysql' => 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', 'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT'],
            'uint'       => ['mysql' => 'INT UNSIGNED', 'sqlite' => 'INTEGER'],
            'int'        => ['mysql' => 'INT', 'sqlite' => 'INTEGER'],
            'tinyint'    => ['mysql' => 'TINYINT', 'sqlite' => 'INTEGER'],
            'text'       => ['mysql' => 'TEXT', 'sqlite' => 'TEXT'],
            'mediumtext' => ['mysql' => 'MEDIUMTEXT', 'sqlite' => 'TEXT'],
        ];
        $key = strtolower($logical);

        if (preg_match('/^varchar\((\d+)\)$/', $key, $m)) {
            return $driver === 'sqlite' ? 'TEXT' : 'VARCHAR(' . (int)$m[1] . ')';
        }
        if (preg_match('/^char\((\d+)\)$/', $key, $m)) {
            return $driver === 'sqlite' ? 'TEXT' : 'CHAR(' . (int)$m[1] . ')';
        }
        if (isset($map[$key])) {
            return $map[$key][$driver] ?? $map[$key]['mysql'];
        }
        throw new \RuntimeException('未知逻辑类型：' . $logical);
    }

    /** 建表尾部：MySQL 需要引擎与字符集，SQLite 不需要 */
    public function tableSuffix(): string
    {
        return $this->driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /** 独占锁：文件缓存击穿时用，超时即放弃写缓存 */
    public function lock(string $name, int $timeoutMs = 300): bool
    {
        if ($this->driver !== 'mysql') {
            return true;
        }
        $row = $this->one('SELECT GET_LOCK(?, ?) AS ok', [$name, max(1, intdiv($timeoutMs, 1000))]);
        return (int)($row['ok'] ?? 0) === 1;
    }

    public function unlock(string $name): void
    {
        if ($this->driver === 'mysql') {
            $this->q('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }
}