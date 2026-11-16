<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Models/Post.php
 * @desc 文章查询：只暴露前台与后台都需要的读方法，写入留在后台控制器
 */

namespace Svel\Models;

use Svel\Core\Db;

final class Post
{
    public static function publishedWhere(string $alias = ''): string
    {
        $p = $alias === '' ? '' : $alias . '.';
        return $p . 'status = \'publish\' AND ' . $p . 'published_at > 0 AND ' . $p . 'published_at <= ' . time();
    }

    public static function findBySlug(string $slug, string $type = 'post'): ?array
    {
        $db = Db::inst();
        return $db->one(
            'SELECT * FROM ' . $db->qi($db->table('post')) . ' WHERE ' . $db->qi('slug') . ' = ? AND ' . $db->qi('type') . ' = ? LIMIT 1',
            [$slug, $type]
        );
    }

    public static function findPublishedBySlug(string $slug, string $type = 'post'): ?array
    {
        $post = self::findBySlug($slug, $type);
        if ($post === null || (string)$post['status'] !== 'publish' || (int)$post['published_at'] <= 0 || (int)$post['published_at'] > time()) {
            return null;
        }
        return $post;
    }

    public static function findById(int $id): ?array
    {
        $db = Db::inst();
        return $db->one('SELECT * FROM ' . $db->qi($db->table('post')) . ' WHERE ' . $db->qi('id') . ' = ? LIMIT 1', [$id]);
    }

    /**
     * @param array{page?:int,per?:int,category_id?:int,tag_id?:int,q?:string,type?:string} $opts
     * @return array{items:array<int,array>,total:int,page:int,pages:int,per:int}
     */
    public static function paginate(array $opts = []): array
    {
        $db = Db::inst();
        $page = max(1, (int)($opts['page'] ?? 1));
        $per = max(1, (int)($opts['per'] ?? 10));
        $type = (string)($opts['type'] ?? 'post');
        $where = [self::publishedWhere('p'), 'p.' . $db->qi('type') . ' = ?'];
        $params = [$type];

        if (!empty($opts['category_id'])) {
            $where[] = 'p.' . $db->qi('category_id') . ' = ?';
            $params[] = (int)$opts['category_id'];
        }

        $join = '';
        if (!empty($opts['tag_id'])) {
            $join = ' INNER JOIN ' . $db->qi($db->table('post_tag')) . ' pt ON pt.' . $db->qi('post_id') . ' = p.' . $db->qi('id');
            $where[] = 'pt.' . $db->qi('tag_id') . ' = ?';
            $params[] = (int)$opts['tag_id'];
        }

        $q = trim((string)($opts['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.' . $db->qi('title') . " LIKE ? ESCAPE '!' OR p." . $db->qi('excerpt') . " LIKE ? ESCAPE '!' OR p." . $db->qi('content') . " LIKE ? ESCAPE '!')";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $table = $db->qi($db->table('post')) . ' p';
        $total = (int)$db->val('SELECT COUNT(DISTINCT p.' . $db->qi('id') . ') FROM ' . $table . $join . ' WHERE ' . $whereSql, $params, 0);

        $pages = max(1, (int)ceil($total / $per));
        // 保留请求页码，越界由控制器返回 404，避免重复内容
        $offset = ($page - 1) * $per;

        $sql = 'SELECT p.* FROM ' . $table . $join
            . ' WHERE ' . $whereSql
            . ' ORDER BY p.' . $db->qi('is_top') . ' DESC, p.' . $db->qi('published_at') . ' DESC, p.' . $db->qi('id') . ' DESC'
            . ' LIMIT ' . $per . ' OFFSET ' . $offset;

        return ['items' => $db->all($sql, $params), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
    }

    /** @return array<int,array> 文章标签 */
    public static function tags(int $postId): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT t.* FROM ' . $db->qi($db->table('tag')) . ' t'
            . ' INNER JOIN ' . $db->qi($db->table('post_tag')) . ' pt ON pt.' . $db->qi('tag_id') . ' = t.' . $db->qi('id')
            . ' WHERE pt.' . $db->qi('post_id') . ' = ? ORDER BY t.' . $db->qi('id') . ' ASC',
            [$postId]
        );
    }

    /** 相关文章：同标签优先，不够用同分类补齐 */
    public static function related(array $post, int $limit = 4): array
    {
        $db = Db::inst();
        $id = (int)$post['id'];
        $out = [];

        $tagIds = array_map(static fn(array $t): int => (int)$t['id'], self::tags($id));
        if ($tagIds !== []) {
            $in = implode(',', array_fill(0, count($tagIds), '?'));
            $params = array_merge($tagIds, [$id]);
            $rows = $db->all(
                'SELECT DISTINCT p.* FROM ' . $db->qi($db->table('post')) . ' p'
                . ' INNER JOIN ' . $db->qi($db->table('post_tag')) . ' pt ON pt.' . $db->qi('post_id') . ' = p.' . $db->qi('id')
                . ' WHERE pt.' . $db->qi('tag_id') . ' IN (' . $in . ') AND p.' . $db->qi('id') . ' <> ? AND ' . self::publishedWhere('p')
                . ' ORDER BY p.' . $db->qi('published_at') . ' DESC LIMIT ' . $limit,
                $params
            );
            $out = $rows;
        }

        if (count($out) < $limit) {
            $catId = (int)($post['category_id'] ?? 0);
            if ($catId > 0) {
                $exclude = array_merge([$id], array_map(static fn(array $r): int => (int)$r['id'], $out));
                $in = implode(',', array_fill(0, count($exclude), '?'));
                $rows = $db->all(
                    'SELECT * FROM ' . $db->qi($db->table('post')) . ' p WHERE p.' . $db->qi('category_id') . ' = ? AND p.' . $db->qi('id') . ' NOT IN (' . $in . ') AND ' . self::publishedWhere('p')
                    . ' ORDER BY p.' . $db->qi('published_at') . ' DESC LIMIT ' . ($limit - count($out)),
                    array_merge([$catId], $exclude)
                );
                $out = array_merge($out, $rows);
            }
        }

        return $out;
    }

    public static function incrementViews(int $id): void
    {
        $db = Db::inst();
        $db->exec('UPDATE ' . $db->qi($db->table('post')) . ' SET ' . $db->qi('views') . ' = ' . $db->qi('views') . ' + 1 WHERE ' . $db->qi('id') . ' = ?', [$id]);
    }

    /** @return array{prev:?array,next:?array} */
    public static function neighbours(array $post): array
    {
        $db = Db::inst();
        $time = (int)$post['published_at'];
        $prev = $db->one(
            'SELECT ' . $db->qi('id') . ',' . $db->qi('title') . ',' . $db->qi('slug') . ',' . $db->qi('type') . ' FROM ' . $db->qi($db->table('post'))
            . ' WHERE ' . self::publishedWhere() . ' AND ' . $db->qi('type') . ' = ? AND ' . $db->qi('published_at') . ' < ? ORDER BY ' . $db->qi('published_at') . ' DESC LIMIT 1',
            [(string)$post['type'], $time]
        );
        $next = $db->one(
            'SELECT ' . $db->qi('id') . ',' . $db->qi('title') . ',' . $db->qi('slug') . ',' . $db->qi('type') . ' FROM ' . $db->qi($db->table('post'))
            . ' WHERE ' . self::publishedWhere() . ' AND ' . $db->qi('type') . ' = ? AND ' . $db->qi('published_at') . ' > ? ORDER BY ' . $db->qi('published_at') . ' ASC LIMIT 1',
            [(string)$post['type'], $time]
        );
        return ['prev' => $prev, 'next' => $next];
    }

    public static function countPublished(string $type = 'post'): int
    {
        $db = Db::inst();
        return (int)$db->val(
            'SELECT COUNT(*) FROM ' . $db->qi($db->table('post')) . ' WHERE ' . self::publishedWhere() . ' AND ' . $db->qi('type') . ' = ?',
            [$type],
            0
        );
    }

    /** sitemap 分批取，避免一次装载全部文章 */
    public static function chunk(int $limit, int $offset, string $type = 'post'): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT ' . $db->qi('id') . ',' . $db->qi('slug') . ',' . $db->qi('type') . ',' . $db->qi('updated_at') . ',' . $db->qi('published_at')
            . ' FROM ' . $db->qi($db->table('post'))
            . ' WHERE ' . self::publishedWhere() . ' AND ' . $db->qi('type') . ' = ?'
            . ' ORDER BY ' . $db->qi('published_at') . ' DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            [$type]
        );
    }

    /** @return array<int,array> 独立页面列表 */
    public static function pages(): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT ' . $db->qi('id') . ',' . $db->qi('slug') . ',' . $db->qi('title') . ',' . $db->qi('updated_at')
            . ' FROM ' . $db->qi($db->table('post'))
            . ' WHERE ' . self::publishedWhere() . ' AND ' . $db->qi('type') . ' = ? ORDER BY ' . $db->qi('id') . ' ASC',
            ['page']
        );
    }

    public static function recent(int $limit = 5): array
    {
        $db = Db::inst();
        return $db->all(
            'SELECT * FROM ' . $db->qi($db->table('post')) . ' WHERE ' . self::publishedWhere()
            . ' AND ' . $db->qi('type') . ' = ? ORDER BY ' . $db->qi('published_at') . ' DESC LIMIT ' . max(1, $limit),
            ['post']
        );
    }
}