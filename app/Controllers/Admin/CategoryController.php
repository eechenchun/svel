<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/CategoryController.php
 * @desc 分类维护：改名换 slug 时写 301；删除分类时文章回落未分类
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Db;
use Svel\Core\Redirect;
use Svel\Core\Request;
use Svel\Core\Slug;
use Svel\Models\Category;

final class CategoryController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        Admin::render('分类', 'categories', [
            'rows' => Category::withCounts(),
        ], 'category');
    }

    public function save(Request $req, array $params = []): void
    {
        Admin::guard();
        $db = Db::inst();

        $id = (int)$req->input('id', 0);
        $name = trim((string)$req->input('name', ''));
        if ($name === '') {
            Admin::back(admin_url('category'), 'error', '分类名不能为空');
        }

        $old = $id > 0 ? Category::findById($id) : null;
        $desired = strtolower(trim((string)$req->input('slug', '')));
        if ($desired !== '' && !Slug::valid($desired)) {
            Admin::back(admin_url('category'), 'error', 'slug 只能用小写字母、数字和连字符，且不能是保留字');
        }

        $slug = $desired;
        if ($slug === '') {
            $slug = $old !== null ? (string)$old['slug'] : Slug::make($name, 'cat');
        }

        $exists = (int)$db->val(
            'SELECT COUNT(*) FROM ' . $db->qi($db->table('category')) . ' WHERE ' . $db->qi('slug') . ' = ? AND ' . $db->qi('id') . ' <> ?',
            [$slug, $id],
            0
        );
        if ($exists > 0) {
            Admin::back(admin_url('category'), 'error', '这个 slug 已被其他分类占用');
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => trim((string)$req->input('description', '')),
            'parent_id'   => (int)$req->input('parent_id', 0),
            'sort'        => (int)$req->input('sort', 0),
        ];

        if ($old === null) {
            $db->insert('category', $data);
        } else {
            $db->update('category', $data, $db->qi('id') . ' = ?', [$id]);
            if ($slug !== (string)$old['slug']) {
                try {
                    Redirect::add('category/' . (string)$old['slug'], 'category/' . $slug);
                } catch (\Throwable $e) {
                    Admin::back(admin_url('category'), 'error', '地址映射写入失败：' . $e->getMessage());
                }
            }
        }

        Cache::flush();
        Admin::back(admin_url('category'), 'ok', '分类已保存');
    }

    public function delete(Request $req, array $params = []): void
    {
        Admin::guard();
        $id = (int)$req->input('id', 0);
        $cat = $id > 0 ? Category::findById($id) : null;
        if ($cat === null) {
            Admin::back(admin_url('category'), 'error', '分类不存在');
        }

        $db = Db::inst();
        // 文章不删除，回落到未分类
        $db->exec(
            'UPDATE ' . $db->qi($db->table('post')) . ' SET ' . $db->qi('category_id') . ' = 0 WHERE ' . $db->qi('category_id') . ' = ?',
            [$id]
        );
        $db->delete('category', $db->qi('id') . ' = ?', [$id]);
        Cache::flush();

        Admin::back(admin_url('category'), 'ok', '分类已删除，原文章已归入未分类');
    }
}