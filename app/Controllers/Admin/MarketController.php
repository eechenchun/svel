<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/MarketController.php
 * @desc 后台应用市场：已安装视图、检查更新、账号绑定、安装/启用/设置/卸载
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Cache;
use Svel\Core\Hook;
use Svel\Core\Market;
use Svel\Core\Request;
use Svel\Core\Theme;
use Svel\Models\Option;
use Svel\Models\Plugin;

final class MarketController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        $refresh = (string)$req->query('refresh', '') === '1';
        $items = Market::items($refresh);
        $tab = (string)$req->query('tab', '');
        if (!in_array($tab, ['plugin', 'theme', 'installed'], true)) {
            $tab = '';
        }

        $index = [];
        foreach ($items as $item) {
            $index[(string)$item['type'] . ':' . (string)$item['slug']] = $item;
        }

        $installed = $this->installedRows($index);
        $updates = 0;
        foreach ($installed as $row) {
            if ($row['update'] !== '') {
                $updates++;
            }
        }

        $shown = [];
        if ($tab !== 'installed') {
            foreach ($items as $item) {
                if ($tab !== '' && (string)$item['type'] !== $tab) {
                    continue;
                }
                $shown[] = $item;
            }
        }

        Admin::render('应用市场', 'market', [
            'items'     => $shown,
            'installed' => $installed,
            'index'     => $index,
            'tab'       => $tab,
            'updates'   => $updates,
            'account'   => [
                'token'   => (string)option('market_token', ''),
                'user'    => (string)option('market_user', ''),
                'site_id' => (int)option('market_site_id', 0),
                'url'     => abs_url(''),
            ],
            'marketUrl' => Market::baseUrl(),
            'empty'     => $items === [],
            'counts'    => [
                'plugin' => count(array_filter($items, static fn(array $i): bool => (string)$i['type'] === 'plugin')),
                'theme'  => count(array_filter($items, static fn(array $i): bool => (string)$i['type'] === 'theme')),
                'installed' => count($installed),
            ],
        ], 'market');
    }

    /** 检查更新：强制刷新清单，提示可更新的数量 */
    public function check(Request $req, array $params = []): void
    {
        Admin::guard();
        $items = Market::items(true);
        $index = [];
        foreach ($items as $item) {
            $index[(string)$item['type'] . ':' . (string)$item['slug']] = $item;
        }
        $updates = [];
        foreach ($this->installedRows($index) as $row) {
            if ($row['update'] !== '') {
                $updates[] = $row['name'];
            }
        }
        Cache::flush();

        if ($updates === []) {
            Admin::back(admin_url('market?tab=installed'), 'ok', '已是最新版本，没有可用更新');
        }
        Admin::back(admin_url('market?tab=installed'), 'ok', '发现 ' . count($updates) . ' 个可更新：' . implode('、', $updates));
    }

    public function update(Request $req, array $params = []): void
    {
        Admin::guard();
        $type = (string)$req->input('type', '');
        $slug = (string)$req->input('slug', '');
        $item = in_array($type, ['plugin', 'theme'], true) ? Market::find($type, $slug) : null;
        if ($item === null) {
            Admin::back(admin_url('market?tab=installed'), 'error', '市场里找不到这个应用的新版本');
        }

        $pkg = Market::download($item);
        if (!$pkg['ok']) {
            Admin::back(admin_url('market?tab=installed'), 'error', $pkg['msg']);
        }

        try {
            $result = $type === 'plugin'
                ? Plugin::installZip($pkg['file'], true)
                : Theme::installZip($pkg['file'], true);
        } finally {
            @unlink($pkg['file']);
        }

        Cache::flush();
        Admin::back(admin_url('market?tab=installed'), $result['ok'] ? 'ok' : 'error', $result['msg']);
    }

    public function install(Request $req, array $params = []): void
    {
        Admin::guard();
        $type = (string)$req->input('type', '');
        $slug = (string)$req->input('slug', '');
        $item = in_array($type, ['plugin', 'theme'], true) ? Market::find($type, $slug) : null;
        if ($item === null) {
            Admin::back(admin_url('market'), 'error', '市场里找不到这个应用');
        }

        $pkg = Market::download($item);
        if (!$pkg['ok']) {
            Admin::back(admin_url('market'), 'error', $pkg['msg']);
        }

        try {
            $result = $type === 'plugin'
                ? Plugin::installZip($pkg['file'])
                : Theme::installZip($pkg['file']);
        } finally {
            @unlink($pkg['file']);
        }

        Cache::flush();
        Admin::back(admin_url('market'), $result['ok'] ? 'ok' : 'error', $result['msg']);
    }

    public function toggle(Request $req, array $params = []): void
    {
        Admin::guard();
        $type = (string)$req->input('type', '');
        $slug = (string)$req->input('slug', '');

        if ($type === 'plugin') {
            $row = Plugin::findBySlug($slug);
            if ($row === null) {
                Admin::back(admin_url('market?tab=installed'), 'error', '插件未安装');
            }
            $enabled = Plugin::toggle($slug);
            Admin::back(admin_url('market?tab=installed'), 'ok', $enabled ? '插件已启用' : '插件已停用');
        }

        if ($type === 'theme') {
            $exists = false;
            $isActive = false;
            foreach (Theme::all() as $row) {
                if ($row['slug'] === $slug) {
                    $exists = true;
                    $isActive = $row['status'] === 1;
                }
            }
            if (!$exists) {
                Admin::back(admin_url('market?tab=installed'), 'error', '主题未安装');
            }
            if ($isActive) {
                if ($slug === 'default') {
                    Admin::back(admin_url('market?tab=installed'), 'error', '默认主题不能停用，请先启用其他主题');
                }
                Theme::activate('default');
                Admin::back(admin_url('market?tab=installed'), 'ok', '已切换到默认主题');
            }
            if (!Theme::activate($slug)) {
                Admin::back(admin_url('market?tab=installed'), 'error', '主题启用失败');
            }
            Admin::back(admin_url('market?tab=installed'), 'ok', '主题已启用');
        }

        Admin::back(admin_url('market?tab=installed'), 'error', '未知应用类型');
    }

    public function uninstall(Request $req, array $params = []): void
    {
        Admin::guard();
        $type = (string)$req->input('type', '');
        $slug = (string)$req->input('slug', '');

        $result = $type === 'plugin' ? Plugin::uninstall($slug) : Theme::uninstall($slug);
        if (!$result['ok']) {
            Admin::back(admin_url('market?tab=installed'), 'error', $result['msg']);
        }

        Option::removePrefix('app.' . $slug . '.');
        Hook::rebuild();
        Cache::flush();

        Admin::back(admin_url('market?tab=installed'), 'ok', $result['msg']);
    }

    public function settings(Request $req, array $params = []): void
    {
        Admin::require();
        $type = (string)$req->input('type', '');
        $slug = (string)$req->input('slug', '');
        if (!in_array($type, ['plugin', 'theme'], true) || $slug === '') {
            Admin::back(admin_url('market'), 'error', '参数不完整');
        }

        if (!$this->isEnabled($type, $slug)) {
            Admin::back(admin_url('market'), 'error', '应用尚未启用，启用后才能设置');
        }

        $schema = $this->schema($type, $slug);
        if ($req->isPost()) {
            \Svel\Core\Csrf::assert();
            $saved = 0;
            foreach ($schema as $field) {
                $key = (string)($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $value = $req->input('cfg_' . $key, null);
                if ($value === null) {
                    continue;
                }
                $checked = $this->validateField($field, (string)$value);
                if ($checked === null) {
                    Admin::back(admin_url('market/settings?type=' . $type . '&slug=' . $slug), 'error', '「' . (string)($field['label'] ?? $key) . '」的取值不合法');
                }
                Option::set('app.' . $slug . '.' . $key, $checked);
                $saved++;
            }
            Cache::flush();
            Admin::back(admin_url('market/settings?type=' . $type . '&slug=' . $slug), 'ok', '已保存 ' . $saved . ' 项设置');
        }

        Admin::render('应用设置', 'market-settings', [
            'type'   => $type,
            'slug'   => $slug,
            'schema' => $schema,
            'values' => $this->values($slug, $schema),
            'name'   => $this->appName($type, $slug),
        ], 'market');
    }

    /** 登录市场账号并自动绑定当前站点 */
    public function loginMarket(Request $req, array $params = []): void
    {
        Admin::guard();
        $username = trim((string)$req->input('username', ''));
        $password = (string)$req->input('password', '');
        if ($username === '' || $password === '') {
            Admin::back(admin_url('market'), 'error', '请填写市场账号与密码');
        }

        $result = Market::login($username, $password);
        if (!$result['ok']) {
            Admin::back(admin_url('market'), 'error', $result['msg']);
        }

        Option::set('market_token', $result['token']);
        Option::set('market_user', $result['username']);

        $bind = Market::bindSite($result['token'], abs_url(''), (string)option('site.title', 'SvelCMS'));
        if ($bind['ok']) {
            Option::set('market_site_id', (string)$bind['site_id']);
            Option::set('market_bind_token', $bind['bind_token']);
            Admin::back(admin_url('market'), 'ok', '已登录 ' . $result['username'] . '，并绑定当前站点');
        }

        Admin::back(admin_url('market'), 'error', '登录成功，但绑定站点失败：' . $bind['msg']);
    }

    public function unbind(Request $req, array $params = []): void
    {
        Admin::guard();
        Option::removePrefix('market_token');
        Option::removePrefix('market_site_id');
        Option::removePrefix('market_bind_token');
        Option::removePrefix('market_user');
        Admin::back(admin_url('market'), 'ok', '已解除本站与市场账号的绑定（市场侧记录仍保留，可在市场后台删除）');
    }

    /**
     * 本地已安装应用，附带市场最新版本与是否可更新
     * @param array<string,array> $index
     * @return array<int,array<string,mixed>>
     */
    private function installedRows(array $index): array
    {
        $rows = [];
        foreach (Plugin::all() as $row) {
            $key = 'plugin:' . $row['slug'];
            $rows[] = $this->row($key, 'plugin', $row, $index);
        }
        foreach (Theme::all() as $row) {
            $key = 'theme:' . $row['slug'];
            $rows[] = $this->row($key, 'theme', $row, $index);
        }
        usort($rows, static fn(array $a, array $b): int => [$a['type'], $a['slug']] <=> [$b['type'], $b['slug']]);
        return $rows;
    }

    private function row(string $key, string $type, array $row, array $index): array
    {
        $market = $index[$key] ?? null;
        $localVersion = (string)($row['version'] ?? '0');
        $remoteVersion = $market !== null ? (string)$market['version'] : '';
        $update = ($remoteVersion !== '' && version_compare($remoteVersion, $localVersion, '>')) ? $remoteVersion : '';

        if ($type === 'plugin') {
            $name = (string)($row['name'] ?? $row['slug']);
            $enabled = (int)$row['status'] === 1;
            $icon = (string)($market['icon'] ?? '');
            $thumb = '';
        } else {
            $name = (string)($row['name'] ?? $row['slug']);
            $enabled = ($row['status'] ?? 0) === 1;
            $icon = '';
            $thumb = (string)($market['thumbnail'] ?? '');
        }

        return [
            'type'    => $type,
            'slug'    => (string)$row['slug'],
            'name'    => $name,
            'version' => $localVersion,
            'enabled' => $enabled,
            'icon'    => $icon,
            'thumb'   => $thumb,
            'market'  => $market !== null,
            'update'  => $update,
        ];
    }

    private function isEnabled(string $type, string $slug): bool
    {
        if ($type === 'plugin') {
            $row = Plugin::findBySlug($slug);
            return $row !== null && (int)$row['status'] === 1;
        }
        return Theme::active() === $slug;
    }

    private function schema(string $type, string $slug): array
    {
        $file = $type === 'plugin'
            ? SVEL_ROOT . '/plugins/' . basename($slug) . '/plugin.json'
            : SVEL_ROOT . '/themes/' . basename($slug) . '/theme.json';
        if (!is_file($file)) {
            return [];
        }
        $meta = json_decode((string)@file_get_contents($file), true);
        return is_array($meta) && is_array($meta['settings'] ?? null) ? $meta['settings'] : [];
    }

    private function appName(string $type, string $slug): string
    {
        $file = $type === 'plugin'
            ? SVEL_ROOT . '/plugins/' . basename($slug) . '/plugin.json'
            : SVEL_ROOT . '/themes/' . basename($slug) . '/theme.json';
        $meta = is_file($file) ? json_decode((string)@file_get_contents($file), true) : null;
        return (string)(is_array($meta) ? ($meta['name'] ?? $slug) : $slug);
    }

    private function values(string $slug, array $schema): array
    {
        $out = [];
        foreach ($schema as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[$key] = (string)app_setting($slug, $key, (string)($field['default'] ?? ''));
        }
        return $out;
    }

    private function validateField(array $field, string $value): ?string
    {
        $type = (string)($field['type'] ?? 'text');
        if ($type === 'number') {
            if ($value !== '' && !preg_match('/^-?\d+$/', $value)) {
                return null;
            }
            return $value === '' ? '0' : $value;
        }
        if ($type === 'select') {
            $allowed = [];
            foreach ((array)($field['options'] ?? []) as $opt) {
                $allowed[] = (string)(is_array($opt) ? ($opt['value'] ?? '') : $opt);
            }
            return in_array($value, $allowed, true) ? $value : null;
        }
        if ($type === 'switch') {
            return $value === '1' ? '1' : '0';
        }
        return mb_substr($value, 0, 500, 'UTF-8');
    }
}