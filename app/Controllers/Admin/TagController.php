<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/TagController.php
 * @desc 标签维护：改名换 slug 时写 301，删除标签时解除与文章的关联
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Db;
use Svel\Core\Redirect;
use Svel\Core\Request;
use Svel\Core\Slug;
use Svel\Models\Tag;

final class TagController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        Admin::render('标签', 'tags', ['rows' => Tag::withCounts()], 'tag');
    }

    public function save(Request $req, array $params = []): void
    {
        Admin::guard();
        $db = Db::inst();

        $id = (int)$req->input('id', 0);
        $name = trim((string)$req->input('name', ''));
        if ($name === '') {
            Admin::back(admin_url('tag'), 'error', '标签名不能为空');
        }

        $old = $id > 0 ? Tag::findById($id) : null;
        $desired = strtolower(trim((string)$req->input('slug', '')));
        if ($desired !== '' && !Slug::valid($desired)) {
            Admin::back(admin_url('tag'), 'error', 'slug 只能用小写字母、数字和连字符，且不能是保留字');
        }
        $slug = $desired !== '' ? $desired : ($old !== null ? (string)$old['slug'] : Slug::make($name, 'tag'));

        $exists = (int)$db->val(
            'SELECT COUNT(*) FROM ' . $db->qi($db->table('tag')) . ' WHERE ' . $db->qi('slug') . ' = ? AND ' . $db->qi('id') . ' <> ?',
            [$slug, $id],
            0
        );
        if ($exists > 0) {
            Admin::back(admin_url('tag'), 'error', '这个 slug 已被其他标签占用');
        }

        if ($old === null) {
            $db->insert('tag', ['name' => $name, 'slug' => $slug, 'created_at' => time()]);
        } else {
            $db->update('tag', ['name' => $name, 'slug' => $slug], $db->qi('id') . ' = ?', [$id]);
            if ($slug !== (string)$old['slug']) {
                try {
                    Redirect::add('tag/' . (string)$old['slug'], 'tag/' . $slug);
                } catch (\Throwable $e) {
                    Admin::back(admin_url('tag'), 'error', '地址映射写入失败：' . $e->getMessage());
                }
            }
        }

        Cache::flush();
        Admin::back(admin_url('tag'), 'ok', '标签已保存');
    }

    public function delete(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        $tag = $id > 0 ? Tag::findById($id) : null;
        if ($tag === null) {
            Admin::back(admin_url('tag'), 'error', '标签不存在');
        }

        $db = Db::inst();
        $db->delete('post_tag', $db->qi('tag_id') . ' = ?', [$id]);
        $db->delete('tag', $db->qi('id') . ' = ?', [$id]);
        Cache::flush();

        Admin::back(admin_url('tag'), 'ok', '标签已删除');
    }
}