<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Schema.php
 * @desc 表结构唯一定义处，安装器与升级都从这里生成 DDL，避免两处各写一套
 */

namespace Svel\Core;

final class Schema
{
    /**
     * 列定义用「逻辑类型 + 约束后缀」，逻辑类型交给 Db::type 翻译。
     *
     * @return array<string,array<string,mixed>>
     */
    public static function tables(): array
    {
        return [
            'post' => [
                'columns' => [
                    'id'             => 'pk',
                    'title'          => 'varchar(255) NOT NULL',
                    'slug'           => 'varchar(191) NOT NULL',
                    'content'        => 'mediumtext',
                    'content_format' => "varchar(10) NOT NULL DEFAULT 'markdown'",
                    'excerpt'        => 'varchar(500)',
                    'cover'          => 'varchar(255)',
                    'status'         => "varchar(10) NOT NULL DEFAULT 'draft'",
                    'type'           => "varchar(10) NOT NULL DEFAULT 'post'",
                    'category_id'    => 'uint NOT NULL DEFAULT 0',
                    'author_id'      => 'uint NOT NULL DEFAULT 0',
                    'views'          => 'uint NOT NULL DEFAULT 0',
                    'is_top'         => 'tinyint NOT NULL DEFAULT 0',
                    'seo_title'      => 'varchar(255)',
                    'seo_desc'       => 'varchar(255)',
                    'seo_keywords'   => 'varchar(255)',
                    'created_at'     => 'uint NOT NULL DEFAULT 0',
                    'updated_at'     => 'uint NOT NULL DEFAULT 0',
                    'published_at'   => 'uint NOT NULL DEFAULT 0',
                ],
                'unique'  => [['slug']],
                'index'   => [['status', 'published_at'], ['type', 'status', 'published_at'], ['category_id'], ['type']],
            ],

            'category' => [
                'columns' => [
                    'id'          => 'pk',
                    'name'        => 'varchar(100) NOT NULL',
                    'slug'        => 'varchar(191) NOT NULL',
                    'description' => 'varchar(255)',
                    'parent_id'   => 'uint NOT NULL DEFAULT 0',
                    'sort'        => 'int NOT NULL DEFAULT 0',
                ],
                'unique' => [['slug']],
                'index'  => [['sort']],
            ],

            'tag' => [
                'columns' => [
                    'id'   => 'pk',
                    'name' => 'varchar(100) NOT NULL',
                    'slug' => 'varchar(191) NOT NULL',
                    'created_at' => 'uint NOT NULL DEFAULT 0',
                ],
                'unique' => [['slug']],
                'index'  => [['name']],
            ],

            'post_tag' => [
                'columns' => [
                    'post_id' => 'uint NOT NULL',
                    'tag_id'  => 'uint NOT NULL',
                ],
                'primary' => ['post_id', 'tag_id'],
                'index'   => [['tag_id']],
                'foreign' => [
                    ['post_id', 'post', 'id'],
                    ['tag_id', 'tag', 'id'],
                ],
            ],

            'option' => [
                'columns' => [
                    'name'  => 'varchar(191) NOT NULL',
                    'value' => 'mediumtext',
                ],
                'primary' => ['name'],
            ],

            'user' => [
                'columns' => [
                    'id'            => 'pk',
                    'username'      => 'varchar(64) NOT NULL',
                    'password_hash' => 'varchar(255) NOT NULL',
                    'nickname'      => 'varchar(64)',
                    'email'         => 'varchar(120)',
'role'          => "varchar(20) NOT NULL DEFAULT 'admin'",
                    'totp_secret'   => "varchar(64) NOT NULL DEFAULT ''",
                    'totp_enabled'  => 'tinyint NOT NULL DEFAULT 0',
                    'totp_recovery' => 'text',
                    'created_at'    => 'uint NOT NULL DEFAULT 0',
                ],
                'unique' => [['username']],
            ],

            'plugin' => [
                'columns' => [
                    'slug'         => 'varchar(64) NOT NULL',
                    'name'         => 'varchar(100)',
                    'version'      => 'varchar(20)',
                    'status'       => 'tinyint NOT NULL DEFAULT 0',
                    'hooks'        => 'text',
                    'source'       => "varchar(20) NOT NULL DEFAULT 'upload'",
                    'priority'     => 'int NOT NULL DEFAULT 10',
                    'checksum'     => 'char(64)',
                    'installed_at' => 'uint NOT NULL DEFAULT 0',
                ],
                'primary' => ['slug'],
            ],

            'theme' => [
                'columns' => [
                    'slug'         => 'varchar(64) NOT NULL',
                    'name'         => 'varchar(100)',
                    'version'      => 'varchar(20)',
                    'status'       => 'tinyint NOT NULL DEFAULT 0',
                    'installed_at' => 'uint NOT NULL DEFAULT 0',
                ],
                'primary' => ['slug'],
            ],

            'nav' => [
                'columns' => [
                    'id'         => 'pk',
                    'title'      => 'varchar(100) NOT NULL',
                    'url'        => "varchar(255) NOT NULL DEFAULT ''",
                    'sort'       => 'int NOT NULL DEFAULT 0',
                    'status'     => 'tinyint NOT NULL DEFAULT 1',
                    'target'     => "varchar(10) NOT NULL DEFAULT '_self'",
                    'created_at' => 'uint NOT NULL DEFAULT 0',
                ],
                'index' => [['sort']],
            ],

            // 1.0 不开放入驻，表先留着，避免 1.1 再动结构
'developer_app' => [
                'columns' => [
                    'id'         => 'pk',
                    'name'       => 'varchar(100)',
                    'email'      => 'varchar(120)',
                    'site'       => 'varchar(255)',
                    'intro'      => 'text',
                    'status'     => "varchar(20) NOT NULL DEFAULT 'pending'",
                    'created_at' => 'uint NOT NULL DEFAULT 0',
                ],
            ],

            'audit' => [
                'columns' => [
                    'id'         => 'pk',
                    'user_id'    => 'uint NOT NULL DEFAULT 0',
                    'action'     => 'varchar(64) NOT NULL',
                    'target'     => 'varchar(255)',
                    'detail'     => 'text',
                    'ip'         => 'varchar(45)',
                    'created_at' => 'uint NOT NULL DEFAULT 0',
                ],
                'index' => [['created_at'], ['user_id'], ['action']],
            ],
        ];
    }

    /** 把「逻辑类型 + 约束后缀」拆开，如 varchar(191) NOT NULL */
    public static function split(string $definition): array
    {
        if (preg_match('/^([a-zA-Z]+(?:\(\d+\))?)(.*)$/', trim($definition), $m)) {
            return [$m[1], $m[2]];
        }
        return [trim($definition), ''];
    }

    /** @return array<int,string> */
    public static function ddl(string $driver, string $prefix): array
    {
        $db = Db::inst();
        $statements = [];

        foreach (self::tables() as $table => $def) {
            $physical = $prefix . $table;
            $cols = [];
            foreach ($def['columns'] as $name => $def2) {
                [$type, $rest] = self::split($def2);
                $cols[] = $db->qi($name) . ' ' . $db->type($type, $driver) . $rest;
            }

            if (isset($def['primary'])) {
                $cols[] = 'PRIMARY KEY (' . implode(',', array_map(
                    static fn(string $c): string => $db->qi($c),
                    $def['primary']
                )) . ')';
            }

            if ($driver === 'mysql') {
                foreach ($def['unique'] ?? [] as $group) {
                    $cols[] = 'UNIQUE KEY ' . $db->qi('uniq_' . $table . '_' . implode('_', $group))
                        . ' (' . implode(',', array_map(static fn(string $c): string => $db->qi($c), $group)) . ')';
                }
                foreach ($def['index'] ?? [] as $group) {
                    $cols[] = 'KEY ' . $db->qi('idx_' . $table . '_' . implode('_', $group))
                        . ' (' . implode(',', array_map(static fn(string $c): string => $db->qi($c), $group)) . ')';
                }
foreach ($def['foreign'] ?? [] as [$col, $refTable, $refCol]) {
                    // 约束名在 MySQL 里是整库唯一，必须带前缀，否则同库多站点会撞名
                    $cols[] = 'CONSTRAINT ' . $db->qi('fk_' . $prefix . $table . '_' . $col)
                        . ' FOREIGN KEY (' . $db->qi($col) . ') REFERENCES ' . $db->qi($prefix . $refTable)
                        . ' (' . $db->qi($refCol) . ') ON DELETE CASCADE';
                }
            } else {
                foreach ($def['foreign'] ?? [] as [$col, $refTable, $refCol]) {
                    $cols[] = 'FOREIGN KEY (' . $db->qi($col) . ') REFERENCES ' . $db->qi($prefix . $refTable)
                        . ' (' . $db->qi($refCol) . ') ON DELETE CASCADE';
                }
            }

            $statements[] = 'CREATE TABLE IF NOT EXISTS ' . $db->qi($physical)
                . ' (' . implode(', ', $cols) . ')' . $db->tableSuffix();

            if ($driver !== 'mysql') {
                foreach ($def['unique'] ?? [] as $group) {
                    $statements[] = 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $db->qi('uniq_' . $table . '_' . implode('_', $group))
                        . ' ON ' . $db->qi($physical)
                        . ' (' . implode(',', array_map(static fn(string $c): string => $db->qi($c), $group)) . ')';
                }
                foreach ($def['index'] ?? [] as $group) {
                    $statements[] = 'CREATE INDEX IF NOT EXISTS ' . $db->qi('idx_' . $table . '_' . implode('_', $group))
                        . ' ON ' . $db->qi($physical)
                        . ' (' . implode(',', array_map(static fn(string $c): string => $db->qi($c), $group)) . ')';
                }
            }
        }

        return $statements;
    }

    /** @return array<int,string> 建表顺序，删除时逆序 */
    public static function tableNames(): array
    {
        return array_keys(self::tables());
    }

    /**
     * 幂等同步表结构：建缺失的表、补缺失的列、补索引。
     * 升级时调用，避免新版本新增表/字段导致 500。
     * @return array{created:array<int,string>,columns:array<int,string>,errors:array<int,string>}
     */
    public static function sync(): array
    {
        $db = Db::inst();
        $driver = $db->driver();
        $prefix = $db->prefix();
        $created = [];
        $columns = [];
        $indexes = [];
        $errors = [];

        foreach (self::ddl($driver, $prefix) as $sql) {
            try {
                $db->pdo()->exec($sql);
            } catch (\Throwable $e) {
                // 索引已存在等重复错误可忽略，建表失败才记录
                if (str_starts_with($sql, 'CREATE TABLE')) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        foreach (self::tables() as $table => $def) {
            $physical = $prefix . $table;
            $existing = self::columnsOf($physical);
            if ($existing === []) {
                continue;
            }
            foreach ($def['columns'] as $name => $definition) {
                if (isset($existing[strtolower($name)])) {
                    continue;
                }
                [$type, $rest] = self::split($definition);
                $sql = 'ALTER TABLE ' . $db->qi($physical) . ' ADD COLUMN ' . $db->qi($name) . ' '
                    . $db->type($type, $driver) . $rest;
                try {
                    $db->pdo()->exec($sql);
                    $columns[] = $table . '.' . $name;
                } catch (\Throwable $e) {
                    $errors[] = $table . '.' . $name . '：' . $e->getMessage();
                }
            }
        }

        if ($driver === 'mysql') {
            foreach (self::tables() as $table => $def) {
                $physical = $prefix . $table;
                if (self::columnsOf($physical) === []) {
                    continue;
                }
                $have = self::indexesOf($physical);
                foreach ([['unique', 'uniq_'], ['index', 'idx_']] as [$kind, $prefixName]) {
                    foreach ($def[$kind] ?? [] as $group) {
                        $name = $prefixName . $table . '_' . implode('_', $group);
                        if (isset($have[$name])) {
                            continue;
                        }
                        $sql = 'ALTER TABLE ' . $db->qi($physical)
                            . ($kind === 'unique' ? ' ADD UNIQUE KEY ' : ' ADD KEY ')
                            . $db->qi($name) . ' (' . implode(',', array_map(static fn(string $c): string => $db->qi($c), $group)) . ')';
                        try {
                            $db->pdo()->exec($sql);
                            $indexes[] = $table . '.' . $name;
                        } catch (\Throwable $e) {
                            $errors[] = $table . '.' . $name . '：' . $e->getMessage();
                        }
                    }
                }
            }
        }

        return ['created' => $created, 'columns' => $columns, 'indexes' => $indexes, 'errors' => $errors];
    }

    /** @return array<string,array<int,string>> 索引名 => 列 */
    private static function indexesOf(string $table): array
    {
        $db = Db::inst();
        $out = [];
        try {
            $rows = $db->all('SHOW INDEX FROM ' . $db->qi($table));
            foreach ($rows as $row) {
                $name = (string)($row['Key_name'] ?? '');
                if ($name === '' || $name === 'PRIMARY') {
                    continue;
                }
                $out[$name][(int)($row['Seq_in_index'] ?? 0)] = (string)($row['Column_name'] ?? '');
            }
        } catch (\Throwable) {
            return [];
        }
        return $out;
    }

    /** @return array<string,string> 列名 => 类型 */
    private static function columnsOf(string $table): array
    {
        $db = Db::inst();
        $out = [];
        try {
            if ($db->driver() === 'sqlite') {
                $rows = $db->all('PRAGMA table_info(' . $db->qi($table) . ')');
                foreach ($rows as $row) {
                    $out[strtolower((string)$row['name'])] = (string)$row['type'];
                }
                return $out;
            }
            $rows = $db->all(
                'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            foreach ($rows as $row) {
                $out[strtolower((string)$row['name'])] = '';
            }
        } catch (\Throwable) {
            return [];
        }
        return $out;
    }
}
