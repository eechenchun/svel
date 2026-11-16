<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/PluginController.php
 * @desc 插件管理：启用/停用、上传安装、重新扫描；编辑器插件互斥，只能有一个接管编辑区
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Request;
use Svel\Models\Plugin;

final class PluginController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        Admin::render('插件', 'plugins', [
            'rows'     => Plugin::all(),
            'provider' => Plugin::editorProvider(),
        ], 'plugin');
    }

    public function toggle(Request $req, array $params = []): void
    {
        Admin::guard();
        $slug = (string)$req->input('slug', '');
        $plugin = Plugin::findBySlug($slug);
        if ($plugin === null) {
            Admin::back(admin_url('plugin'), 'error', '插件不存在');
        }

        $willEnable = (int)$plugin['status'] !== 1;
        if ($willEnable && $this->isEditor($slug)) {
            // 编辑器互斥：先停用其他接线到 admin_editor 的插件
            foreach (Plugin::enabledWithHooks() as $other) {
                if ((string)$other['slug'] !== $slug && $this->isEditor((string)$other['slug'])) {
                    Plugin::toggle((string)$other['slug']);
                }
            }
        }

        $enabled = Plugin::toggle($slug);
        Admin::back(admin_url('plugin'), 'ok', $enabled ? '插件已启用' : '插件已停用');
    }

    public function scan(Request $req, array $params = []): void
    {
        Admin::guard();
        $added = Plugin::discover();
        Admin::back(admin_url('plugin'), 'ok', '已扫描 plugins 目录，新登记 ' . $added . ' 个插件');
    }

    public function upload(Request $req, array $params = []): void
    {
        Admin::guard();
        $file = $_FILES['package'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Admin::back(admin_url('plugin'), 'error', '没有收到插件包，或上传中断');
        }
        if ((int)$file['size'] > 10 * 1024 * 1024) {
            Admin::back(admin_url('plugin'), 'error', '插件包不能超过 10MB');
        }
        $name = (string)($file['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            Admin::back(admin_url('plugin'), 'error', '插件包必须是 .zip');
        }

        $result = Plugin::installZip((string)$file['tmp_name']);
        Admin::back(admin_url('plugin'), $result['ok'] ? 'ok' : 'error', $result['msg']);
    }

    private function isEditor(string $slug): bool
    {
        $manifest = Plugin::manifest($slug);
        $hooks = $manifest['hooks'] ?? [];
        $names = is_array($hooks) ? (array_is_list($hooks) ? $hooks : array_keys($hooks)) : [];
        return in_array('admin_editor', $names, true);
    }
}