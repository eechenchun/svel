<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/PreviewController.php
 * @desc 编辑器实时预览：返回 HTML 片段 + 目录 + 字数，不写页面缓存
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Auth;
use Svel\Core\Csrf;
use Svel\Core\Html;
use Svel\Core\Markdown;
use Svel\Core\Request;
use Svel\Core\Response;

final class PreviewController
{
    public function render(Request $req, array $params = []): void
    {
        if (Auth::user() === null) {
            Response::json(['ok' => false, 'msg' => '未登录'], 401);
        }
        $token = (string)($req->input('_token', '') ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!Csrf::check($token)) {
            Response::json(['ok' => false, 'msg' => '令牌失效，请刷新页面'], 419);
        }

        $markdown = (string)$req->input('content', '');
        $html = Html::clean(Markdown::toHtml($markdown));
        $plain = preg_replace('/\s+/u', '', strip_tags($html)) ?? '';

        Response::json([
            'ok'      => true,
            'html'    => $html,
            'outline' => Markdown::outline($markdown),
            'count'   => mb_strlen($plain, 'UTF-8'),
        ]);
    }
}