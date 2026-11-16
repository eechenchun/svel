<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Controllers/Admin/UploadController.php
 * @desc 正文插图上传：扩展名白名单、随机文件名、图片二次校验，返回可直接插入的地址
 */

namespace Svel\Controllers\Admin;

use Svel\Core\Auth;
use Svel\Core\Config;
use Svel\Core\Csrf;
use Svel\Core\Request;
use Svel\Core\Response;

final class UploadController
{
    private const EXT_POOL = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];

    public function upload(Request $req, array $params = []): void
    {
        if (Auth::user() === null) {
            Response::json(['ok' => false, 'msg' => '未登录'], 401);
        }
        $token = (string)($req->input('_token', '') ?: ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!Csrf::check($token)) {
            Response::json(['ok' => false, 'msg' => '令牌失效，请刷新页面'], 419);
        }

        $maxMb = max(1, min(20, (int)option('upload.max_mb', 5)));
        $allowed = [];
        $extOption = strtolower((string)option('upload.exts', 'jpg,jpeg,png,gif,webp,ico'));
        foreach (explode(',', $extOption) as $item) {
            $item = trim($item, " \t\n\r\0\x0B.");
            if (in_array($item, self::EXT_POOL, true)) {
                $allowed[] = $item;
            }
        }
        if ($allowed === []) {
            $allowed = self::EXT_POOL;
        }

        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::json(['ok' => false, 'msg' => '没有收到文件，或上传中断'], 400);
        }
        if ((int)$file['size'] > $maxMb * 1024 * 1024) {
            Response::json(['ok' => false, 'msg' => '图片不能超过 ' . $maxMb . 'MB'], 400);
        }

        $name = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            Response::json(['ok' => false, 'msg' => '只允许 ' . implode(' / ', $allowed) . ' 格式'], 400);
        }

        $tmp = (string)$file['tmp_name'];
        $info = @getimagesize($tmp);
        if ($info === false) {
            Response::json(['ok' => false, 'msg' => '这个文件不是有效图片'], 400);
        }

        $uploadDir = (string)Config::get('upload.dir', SVEL_ROOT . '/storage/uploads');
        $uploadUrl = (string)Config::get('upload.url', '/storage/uploads');
        $relDir = date('Y/m');
        $dir = rtrim(str_replace('\\', '/', $uploadDir), '/') . '/' . $relDir;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            Response::json(['ok' => false, 'msg' => '上传目录不可写：' . $dir], 500);
        }

        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $target = rtrim($dir, '/') . '/' . $filename;
        if (!@move_uploaded_file($tmp, $target) && !@copy($tmp, $target)) {
            Response::json(['ok' => false, 'msg' => '保存文件失败，请检查目录权限'], 500);
        }
        @chmod($target, 0644);

        $relative = rtrim(str_replace('\\', '/', $uploadUrl), '/') . '/' . $relDir . '/' . $filename;
        Response::json([
            'ok'     => true,
            'url'    => asset(ltrim($relative, '/')),
            'path'   => $relative,
            'width'  => (int)($info[0] ?? 0),
            'height' => (int)($info[1] ?? 0),
        ]);
    }
}