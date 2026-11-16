<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/UpgradeController.php
 * @desc 系统升级：检查版本、手动升级、自动升级开关、通道与升级记录
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Request;
use Svel\Core\Upgrader;
use Svel\Models\Option;

final class UpgradeController
{
    public function index(Request $req, array $params = []): void
    {
        Admin::require();
        $check = Upgrader::check();
        $history = json_decode((string)option('upgrade_history', '[]'), true);

        Admin::render('系统升级', 'upgrade', [
            'check'      => $check,
            'auto'       => Upgrader::autoEnabled(),
            'channel'    => Upgrader::channel(),
            'lastAt'     => (int)option('last_upgrade_at', 0),
            'lastFrom'   => (string)option('last_upgrade_from', ''),
            'lastTo'     => (string)option('last_upgrade_to', ''),
            'autoResult' => (string)option('auto_upgrade_result', ''),
            'history'    => is_array($history) ? $history : [],
        ], 'upgrade');
    }

    public function check(Request $req, array $params = []): void
    {
        Admin::guard();
        $check = Upgrader::check(true);
        Option::set('last_check_at', (string)time());
        Admin::back(
            admin_url('upgrade'),
            $check['ok'] ? 'ok' : 'error',
            $check['ok']
                ? ($check['has_update']
                    ? '发现' . ($check['channel'] === 'beta' ? '测试版' : '稳定版') . ' v' . $check['latest'] . '（当前 v' . $check['current'] . '）'
                    : '已是最新版本 v' . $check['current'])
                : $check['msg']
        );
    }

    public function upgrade(Request $req, array $params = []): void
    {
        Admin::guard();
        $result = Upgrader::upgrade();
        Admin::back(admin_url('upgrade'), $result['ok'] ? 'ok' : 'error', $result['msg']);
    }

    public function toggle(Request $req, array $params = []): void
    {
        Admin::guard();
        $on = (string)$req->input('auto', '0') === '1';
        $channel = (string)$req->input('channel', 'stable') === 'beta' ? 'beta' : 'stable';
        Option::set('auto_upgrade', $on ? '1' : '0');
        Option::set('upgrade_channel', $channel);
        Option::set('upgrade_latest', '');
        Admin::back(
            admin_url('upgrade'),
            'ok',
            ($on ? '已开启自动升级（后台加载时检查，6 小时最多一次）' : '已关闭自动升级')
            . '；升级通道：' . ($channel === 'beta' ? '测试版' : '稳定版')
        );
    }
}