<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Tag.php
 * @desc 标签：标签页、文章标签与后台维护共用
 */

namespace Svel\Models;

use Svel\Core\Db;
use Svel\Core\Slug;

final class Tag
{
    public static function findBySlug(string $slug): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('tag')) . ' WHERE ' . $db->qi('slug') . ' = ? LIMIT 1', [$slug]);
    }

    public static function findById(int $id): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('tag')) . ' WHERE ' . $db->qi('id') . ' = ? LIMIT 1', [$id]);
    }

    /** 按名字批量取 ID，不存在则创建，供后台保存文章时复用 */
    public static function ensure(array $names): array
    {
        $db = Db::inst();
        $ids = [];
        foreach ($names as $name) {
            $name = mb_substr(trim((string)$name), 0, 100, 'UTF-8');
            if ($name === '') {
                continue;
            }
            $row = $db->one('SELECT * FROM ' . $db->qi($db->table('tag')) . ' WHERE ' . $db->qi('name') . ' = ? LIMIT 1', [$name]);
            if ($row !== null) {
                $ids[] = (int)$row['id'];
                continue;
            }
            $slug = Slug::make($name, 'tag');
            $exists = $db->val('SELECT COUNT(*) FROM ' . $db->qi($db->table('tag')) . ' WHERE ' . $db->qi('slug') . ' = ?', [$slug], 0);
            if ((int)$exists > 0) {
                $slug .= '-' . bin2hex(random_bytes(2));
            }
            $id = $db->insert('tag', ['name' => $name, 'slug' => $slug, 'created_at' => time()]);
            $ids[] = $id;
        }
        return $ids;
    }

    public static function syncPost(int $postId, array $tagIds): void
    {
        $db = Db::inst();
        $db->delete('post_tag', $db->qi('post_id') . ' = ?', [$postId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
            if ($tagId > 0) {
                $db->insert('post_tag', ['post_id' => $postId, 'tag_id' => $tagId]);
            }
        }
    }

    /** 后台标签列表带文章数 */
    public static function withCounts(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT t.*, (SELECT COUNT(*) FROM ' . $db->qi($db->table('post_tag')) . ' pt'
            . ' INNER JOIN ' . $db->qi($db->table('post')) . ' p ON p.' . $db->qi('id') . ' = pt.' . $db->qi('post_id')
            . ' WHERE pt.' . $db->qi('tag_id') . ' = t.' . $db->qi('id') . ' AND ' . Post::publishedWhere('p') . ') AS total'
            . ' FROM ' . $db->qi($db->table('tag')) . ' t ORDER BY t.' . $db->qi('id') . ' DESC'
        );
    }

    /** sitemap 用：只取有已发布文章的标签 */
    public static function withPublished(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT DISTINCT t.' . $db->qi('id') . ', t.' . $db->qi('slug') . ' FROM ' . $db->qi($db->table('tag')) . ' t'
            . ' INNER JOIN ' . $db->qi($db->table('post_tag')) . ' pt ON pt.' . $db->qi('tag_id') . ' = t.' . $db->qi('id')
            . ' INNER JOIN ' . $db->qi($db->table('post')) . ' p ON p.' . $db->qi('id') . ' = pt.' . $db->qi('post_id')
            . ' WHERE ' . Post::publishedWhere('p')
        );
    }
}