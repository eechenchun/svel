<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/TwoFactorController.php
 * @desc 两步验证：登录二次校验、启用/关闭与恢复码
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Admin;
use Svel\Core\Audit;
use Svel\Core\Auth;
use Svel\Core\Csrf;
use Svel\Core\Db;
use Svel\Core\Flash;
use Svel\Core\Request;
use Svel\Core\Response;
use Svel\Core\TwoFactor;
use Svel\Models\User;

final class TwoFactorController
{
    private const MAX_FAILS = 5;

    public function challenge(Request $req, array $params = []): void
    {
        Auth::start();
        $uid = (int)($_SESSION['_svel_2fa_uid'] ?? 0);
        if ($uid <= 0) {
            Response::redirect(admin_url('login'));
        }
        if (Auth::user() !== null) {
            Response::redirect(admin_url(''));
        }

        $user = User::findById($uid);
        if ($user === null) {
            $this->clearPending();
            Response::redirect(admin_url('login'));
        }

        $error = '';
        if ($req->method === 'POST') {
            Csrf::assert();
            $code = trim((string)$req->input('code', ''));
            $secret = (string)($user['totp_secret'] ?? '');
            $ok = TwoFactor::verify($secret, $code);
            $recovery = null;
            if (!$ok) {
                $recovery = TwoFactor::verifyRecovery((string)($user['totp_recovery'] ?? ''), $code);
                $ok = $recovery !== null;
            }

            if ($ok) {
                if ($recovery !== null) {
                    $this->saveTotp((int)$user['id'], $secret, true, $recovery);
                }
                Auth::loginUser($user);
                Audit::log('login_2fa', (string)$user['username'], 'TOTP 通过', (int)$user['id']);
                Response::redirect(admin_url(''));
            }

            $_SESSION['_svel_2fa_fails'] = (int)($_SESSION['_svel_2fa_fails'] ?? 0) + 1;
            if ((int)$_SESSION['_svel_2fa_fails'] >= self::MAX_FAILS) {
                $this->clearPending();
                Flash::set('error', '两步验证失败次数过多，请重新登录');
                Response::redirect(admin_url('login'));
            }
            $error = '验证码或恢复码不正确';
        }

        echo \Svel\Core\View::admin('twofactor', [
            'error'    => $error,
            'username' => (string)$user['username'],
        ]);
    }

    public function setup(Request $req, array $params = []): void
    {
        $user = Admin::require();
        $enabled = TwoFactor::enabled($user);
        $recoveryCodes = [];

        if ($req->method === 'POST') {
            Admin::guard();
            $action = (string)$req->input('action', 'enable');
            $secret = (string)($user['totp_secret'] ?? '');

            if ($action === 'disable') {
                $current = (string)$req->input('current_password', '');
                $code = trim((string)$req->input('code', ''));
                if (!password_verify($current, (string)$user['password_hash'])) {
                    Flash::set('error', '当前密码不正确');
                    Response::redirect(admin_url('2fa/setup'));
                }
                if (!TwoFactor::verify($secret, $code)) {
                    Flash::set('error', '验证码不正确');
                    Response::redirect(admin_url('2fa/setup'));
                }
                $this->saveTotp((int)$user['id'], '', false, '');
                Audit::log('2fa_disabled', (string)$user['username'], '关闭两步验证', (int)$user['id']);
                Flash::set('ok', '已关闭两步验证');
                Response::redirect(admin_url('2fa/setup'));
            }

            $setupSecret = (string)($_SESSION['_svel_2fa_setup'] ?? '');
            $code = trim((string)$req->input('code', ''));
            if ($setupSecret === '' || !TwoFactor::verify($setupSecret, $code)) {
                Flash::set('error', '验证码不正确，请用验证器里显示的 6 位数字');
                Response::redirect(admin_url('2fa/setup'));
            }
            $recovery = TwoFactor::recoveryCodes();
            $this->saveTotp((int)$user['id'], $setupSecret, true, json_encode($recovery['hash']) ?: '[]');
            unset($_SESSION['_svel_2fa_setup']);
            Audit::log('2fa_enabled', (string)$user['username'], '开启两步验证', (int)$user['id']);
            $recoveryCodes = $recovery['plain'];
            $enabled = true;
        }

        $secret = '';
        if (!$enabled) {
            $secret = (string)($_SESSION['_svel_2fa_setup'] ?? '');
            if ($secret === '') {
                $secret = TwoFactor::generateSecret();
                $_SESSION['_svel_2fa_setup'] = $secret;
            }
        }

        Admin::render('两步验证', 'twofactor-setup', [
            'enabled'       => $enabled,
            'secret'        => $secret,
            'uri'           => $secret === '' ? '' : TwoFactor::uri($secret, (string)$user['username']),
            'recoveryCodes' => $recoveryCodes,
        ], 'setting');
    }

    private function saveTotp(int $userId, string $secret, bool $enabled, string $recovery): void
    {
        Db::inst()->update('user', [
            'totp_secret'   => $secret,
            'totp_enabled'  => $enabled ? 1 : 0,
            'totp_recovery' => $recovery,
        ], Db::inst()->qi('id') . ' = ?', [$userId]);
    }

    private function clearPending(): void
    {
        unset($_SESSION['_svel_2fa_uid'], $_SESSION['_svel_2fa_at'], $_SESSION['_svel_2fa_fails']);
    }
}