<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Option.php
 * @desc 站点配置读写，同一请求只查一次库
 */

namespace Svel\Models;

use Svel\Core\Db;

final class Option
{
    private static ?array $rows = null;

    public static function all(): array
    {
        if (self::$rows !== null) {
            return self::$rows;
        }
        $db = Db::inst();
        $rows = [];
        foreach ($db->all('SELECT ' . $db->qi('name') . ',' . $db->qi('value') . ' FROM ' . $db->qi($db->table('option'))) as $row) {
            $rows[(string)$row['name']] = (string)($row['value'] ?? '');
        }
        return self::$rows = $rows;
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        $all = self::all();
        return $all[$name] ?? $default;
    }

    public static function set(string $name, string $value): void
    {
        $db = Db::inst();
        $exists = $db->val(
            'SELECT COUNT(*) FROM ' . $db->qi($db->table('option')) . ' WHERE ' . $db->qi('name') . ' = ?',
            [$name],
            0
        );
        if ((int)$exists > 0) {
            $db->update('option', ['value' => $value], $db->qi('name') . ' = ?', [$name]);
        } else {
            $db->insert('option', ['name' => $name, 'value' => $value]);
        }
        if (self::$rows !== null) {
            self::$rows[$name] = $value;
        }
    }

    public static function forget(): void
    {
        self::$rows = null;
    }

    /** 删除某前缀的全部配置项，卸载应用时清理 app.{slug}. 用 */
    public static function removePrefix(string $prefix): int
    {
        $db = Db::inst();
        $names = [];
        foreach (self::all() as $name => $value) {
            if (str_starts_with((string)$name, $prefix)) {
                $names[] = (string)$name;
            }
        }
        $count = 0;
        foreach ($names as $name) {
            $count += $db->delete('option', $db->qi('name') . ' = ?', [$name]);
        }
        if (self::$rows !== null) {
            foreach ($names as $name) {
                unset(self::$rows[$name]);
            }
        }
        return $count;
    }
}
