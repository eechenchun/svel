<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Category.php
 * @desc 分类：前台导航、分类页与后台维护共用
 */

namespace Svel\Models;

use Svel\Core\Db;

final class Category
{
    public static function all(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT * FROM ' . $db->qi($db->table('category')) . ' ORDER BY ' . $db->qi('sort') . ' ASC, ' . $db->qi('id') . ' ASC'
        );
    }

    /** 带已发布文章数，前台导航用；一次聚合查询，不做 N+1 */
    public static function withCounts(): array
    {
        $db = Db::inst();
        $rows = $db->all(
            'SELECT c.*, (SELECT COUNT(*) FROM ' . $db->qi($db->table('post')) . ' p WHERE p.' . $db->qi('category_id') . ' = c.' . $db->qi('id')
            . ' AND ' . Post::publishedWhere('p') . ') AS total'
            . ' FROM ' . $db->qi($db->table('category')) . ' c ORDER BY c.' . $db->qi('sort') . ' ASC, c.' . $db->qi('id') . ' ASC'
        );
        return $rows;
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('category')) . ' WHERE ' . $db->qi('slug') . ' = ? LIMIT 1', [$slug]);
    }

    public static function findById(int $id): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('category')) . ' WHERE ' . $db->qi('id') . ' = ? LIMIT 1', [$id]);
    }
}