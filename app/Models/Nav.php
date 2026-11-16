<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Nav.php
 * @desc 前台导航：后台维护，前台按排序输出；为空时主题回退到分类导航
 */

namespace Svel\Models;

use Svel\Core\Db;

final class Nav
{
    public static function all(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT * FROM ' . $db->qi($db->table('nav')) . ' ORDER BY ' . $db->qi('sort') . ' ASC, ' . $db->qi('id') . ' ASC'
        );
    }

    public static function active(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT * FROM ' . $db->qi($db->table('nav')) . ' WHERE ' . $db->qi('status') . ' = 1 ORDER BY ' . $db->qi('sort') . ' ASC, ' . $db->qi('id') . ' ASC'
        );
    }

    public static function find(int $id): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('nav')) . ' WHERE ' . $db->qi('id') . ' = ?', [$id]);
    }

    public static function exists(): bool
    {
        $db = Db::inst();
        return (int)$db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('nav')), [], 0) > 0;
    }

    /** 首次启用导航管理时，把默认首页链接写进去，避免前台突然没有导航 */
    public static function seedDefault(): void
    {
        if (self::exists()) {
            return;
        }
        $db = Db::inst();
        $db->insert('nav', [
            'title'      => '首页',
            'url'        => '/',
            'sort'       => 0,
            'status'     => 1,
            'target'     => '_self',
            'created_at' => time(),
        ]);
    }
}