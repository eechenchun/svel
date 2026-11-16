<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/PostController.php
 * @desc 文章与独立页面：保存时处理 slug 改名与 301，保存后清空页面缓存
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Db;
use Svel\Core\Editor;
use Svel\Core\Flash;
use Svel\Core\Html;
use Svel\Core\Pager;
use Svel\Core\Redirect;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\Slug;
use Svel\Models\Category;
use Svel\Models\Post;
use Svel\Models\Tag;

final class PostController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        $db = Db::inst();

        $type = (string)$req->query('type', 'post') === 'page' ? 'page' : 'post';
        $status = (string)$req->query('status', '');
        $q = trim((string)$req->query('q', ''));
$page = max(1, (int)$req->query('p', 1));
        $per = max(5, min(100, (int)option('admin.per_page', 20)));

        $where = ['p.' . $db->qi('type') . ' = ?'];
        $bind = [$type];
        if (in_array($status, ['publish', 'draft'], true)) {
            $where[] = 'p.' . $db->qi('status') . ' = ?';
            $bind[] = $status;
        }
        if ($q !== '') {
            $where[] = 'p.' . $db->qi('title') . ' LIKE ?';
            $bind[] = '%' . $q . '%';
        }
        $whereSql = implode(' AND ', $where);
        $table = $db->qi($db->table('post')) . ' p';

        $total = (int)$db->val('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $whereSql, $bind, 0);
        $pages = max(1, (int)ceil($total / $per));
        $page = min($page, $pages);
        $rows = $db->all(
            'SELECT p.* FROM ' . $table . ' WHERE ' . $whereSql . ' ORDER BY p.' . $db->qi('updated_at') . ' DESC, p.' . $db->qi('id') . ' DESC'
            . ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per),
            $bind
        );

        $categories = [];
        foreach (Category::all() as $cat) {
            $categories[(int)$cat['id']] = (string)$cat['name'];
        }

        Admin::render('文章', 'posts', [
            'rows'       => $rows,
            'categories' => $categories,
            'type'       => $type,
            'status'     => $status,
            'q'          => $q,
            'pager'      => Pager::build($page, $pages, static fn(int $n): string => admin_url('post?type=' . $type . '&status=' . $status . '&q=' . urlencode($q) . '&p=' . $n)),
            'total'      => $total,
        ], 'post');
    }

    public function form(Request $req, array $params = []): void
    {
        Admin::require();
        $id = (int)$req->query('id', 0);
        $post = $id > 0 ? Post::findById($id) : null;
        if ($id > 0 && $post === null) {
            Admin::back(admin_url('post'), 'error', '文章不存在');
        }
        if ($post === null) {
            $post = [
                'id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'content_format' => 'markdown',
                'excerpt' => '', 'cover' => '', 'status' => 'draft', 'type' => 'post',
                'category_id' => 0, 'is_top' => 0, 'seo_title' => '', 'seo_desc' => '', 'seo_keywords' => '',
                'published_at' => 0, 'updated_at' => 0, 'views' => 0,
            ];
        }

        $tagNames = $post['id'] > 0
            ? implode(', ', array_map(static fn(array $t): string => (string)$t['name'], Post::tags((int)$post['id'])))
            : '';

        Admin::render($post['id'] > 0 ? '编辑内容' : '新建内容', 'post-form', [
            'post'         => $post,
            'categories'   => Category::all(),
            'tagNames'     => $tagNames,
            'editor'       => Editor::render($post),
            'editorAssets' => Editor::assets(),
            'editorName'   => Editor::providerName(),
        ], 'post');
    }

    public function save(Request $req, array $params = []): void
    {
        Admin::guard();
        $db = Db::inst();

        $id = (int)$req->input('id', 0);
        $title = mb_substr(trim((string)$req->input('title', '')), 0, 255, 'UTF-8');
        if ($title === '') {
            Admin::back(admin_url('post/edit' . ($id > 0 ? '?id=' . $id : '')), 'error', '标题不能为空');
        }

        $old = $id > 0 ? Post::findById($id) : null;
        $redirectError = '';
        if ($id > 0 && $old === null) {
            Admin::back(admin_url('post'), 'error', '文章不存在');
        }

        $type = (string)$req->input('type', 'post') === 'page' ? 'page' : 'post';
        $status = (string)$req->input('status', 'draft') === 'publish' ? 'publish' : 'draft';
        $format = (string)$req->input('content_format', 'markdown') === 'html' ? 'html' : 'markdown';
        $rawContent = (string)$req->input('content', '');
        $content = $format === 'html' ? Html::clean($rawContent) : trim($rawContent);
        $now = time();

        $desired = strtolower(trim((string)$req->input('slug', '')));
        if ($desired !== '' && !Slug::valid($desired)) {
            Admin::back(
                admin_url('post/edit' . ($id > 0 ? '?id=' . $id : '')),
                'error',
                'slug 只能用小写字母、数字和连字符，且不能是 admin / post / category 这类保留字'
            );
        }

        $publishedAt = (int)$req->input('published_at_ts', 0);
        if ($publishedAt <= 0) {
            $publishedAt = $old !== null ? (int)$old['published_at'] : 0;
        }
        if ($status === 'publish' && $publishedAt <= 0) {
            $publishedAt = $now;
        }
        $dateInput = trim((string)$req->input('published_date', ''));
        if ($dateInput !== '') {
            $ts = strtotime($dateInput);
            if ($ts !== false) {
                $publishedAt = $ts;
            }
        }

        $data = [
            'title'          => $title,
            'content'        => $content,
            'content_format' => $format,
            'excerpt'        => mb_substr(trim((string)$req->input('excerpt', '')), 0, 500, 'UTF-8'),
            'cover'          => mb_substr(trim((string)$req->input('cover', '')), 0, 255, 'UTF-8'),
            'status'         => $status,
            'type'           => $type,
            'category_id'    => (int)$req->input('category_id', 0),
            'author_id'      => (int)($old['author_id'] ?? 0) ?: (int)(\Svel\Core\Auth::user()['id'] ?? 1),
            'is_top'         => (int)$req->input('is_top', 0) === 1 ? 1 : 0,
            'seo_title'      => mb_substr(trim((string)$req->input('seo_title', '')), 0, 255, 'UTF-8'),
            'seo_desc'       => mb_substr(trim((string)$req->input('seo_desc', '')), 0, 255, 'UTF-8'),
            'seo_keywords'   => mb_substr(trim((string)$req->input('seo_keywords', '')), 0, 255, 'UTF-8'),
            'updated_at'     => $now,
            'published_at'   => $publishedAt,
        ];

        if ($id === 0) {
            $slug = $desired !== '' ? $this->uniqueSlug($desired, 0) : Slug::ascii($title);
            if ($slug === '') {
                // 中文标题没有可用 ASCII：先落临时值，拿到自增 ID 后改成 {type}-{id}
                $slug = 'tmp-' . bin2hex(random_bytes(4));
            }
            $data['slug'] = $slug;
            $data['created_at'] = $now;
            $id = $db->insert('post', $data);

            if (str_starts_with($slug, 'tmp-')) {
                $final = Slug::withId($type === 'page' ? 'page' : 'post', $id);
                $db->update('post', ['slug' => $final], $db->qi('id') . ' = ?', [$id]);
            }
        } else {
            $newSlug = (string)$old['slug'];
            if ($desired !== '' && $desired !== (string)$old['slug']) {
                $newSlug = $this->uniqueSlug($desired, $id);
            }
            $data['slug'] = $newSlug;
            $db->update('post', $data, $db->qi('id') . ' = ?', [$id]);

            $oldPath = $this->pathOf((string)$old['type'], (string)$old['slug']);
            $newPath = $this->pathOf($type, $newSlug);
            if ($oldPath !== $newPath) {
                try {
                    Redirect::add($oldPath, $newPath);
                } catch (\Throwable $e) {
                    $redirectError = '文章已保存，但旧地址 301 映射写入失败：' . $e->getMessage();
                }
            }
        }

        if ($type === 'post') {
            $names = preg_split('/[,，\s]+/u', (string)$req->input('tags', '')) ?: [];
            $names = array_values(array_filter(array_map('trim', $names)));
            Tag::syncPost($id, Tag::ensure($names));
        }

        Cache::flush();
        if (!empty($redirectError)) {
            Admin::back(admin_url('post/edit?id=' . $id), 'error', $redirectError);
        }
        Admin::back(admin_url('post/edit?id=' . $id), 'ok', '已保存');
    }

    public function delete(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        $post = $id > 0 ? Post::findById($id) : null;
        if ($post === null) {
            Admin::back(admin_url('post'), 'error', '文章不存在');
        }

        $db = Db::inst();
        Redirect::removeTarget($this->pathOf((string)$post['type'], (string)$post['slug']));
        $db->delete('post_tag', $db->qi('post_id') . ' = ?', [$id]);
        $db->delete('post', $db->qi('id') . ' = ?', [$id]);
        Cache::flush();

        Admin::back(admin_url('post'), 'ok', '已删除「' . (string)$post['title'] . '」');
    }

    public function toggle(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        $post = $id > 0 ? Post::findById($id) : null;
        if ($post === null) {
            Admin::back(admin_url('post'), 'error', '文章不存在');
        }
        $db = Db::inst();
        $newStatus = (string)$post['status'] === 'publish' ? 'draft' : 'publish';
        $data = ['status' => $newStatus, 'updated_at' => time()];
        if ($newStatus === 'publish' && (int)$post['published_at'] <= 0) {
            $data['published_at'] = time();
        }
        $db->update('post', $data, $db->qi('id') . ' = ?', [$id]);
        Cache::flush();

        Admin::back(admin_url('post'), 'ok', $newStatus === 'publish' ? '已发布' : '已转为草稿');
    }

    private function uniqueSlug(string $slug, int $ignoreId): string
    {
        $db = Db::inst();
        $base = $slug;
        $i = 2;
        while ((int)$db->val(
            'SELECT COUNT(*) FROM ' . $db->qi($db->table('post')) . ' WHERE ' . $db->qi('slug') . ' = ? AND ' . $db->qi('id') . ' <> ?',
            [$slug, $ignoreId],
            0
        ) > 0) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 99) {
                $slug = $base . '-' . bin2hex(random_bytes(2));
                break;
            }
        }
        return $slug;
    }

    private function pathOf(string $type, string $slug): string
    {
        return $type === 'page' ? $slug . '.html' : 'post/' . $slug . '.html';
    }
}