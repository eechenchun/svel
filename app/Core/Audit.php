<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Audit.php
 * @desc 后台操作审计：记录谁在什么时间对什么对象做了什么
 */

namespace Svel\Core;

final class Audit
{
    public static function log(string $action, string $target = '', string $detail = '', int $userId = 0): void
    {
        try {
            $db = Db::inst();
            if ($userId <= 0) {
                $user = Auth::user();
                $userId = (int)($user['id'] ?? 0);
            }
            $db->insert('audit', [
                'user_id'    => $userId,
                'action'     => mb_substr($action, 0, 64, 'UTF-8'),
                'target'     => mb_substr($target, 0, 255, 'UTF-8'),
                'detail'     => mb_substr($detail, 0, 2000, 'UTF-8'),
                'ip'         => mb_substr(Request::capture()->ip(), 0, 45, 'UTF-8'),
                'created_at' => time(),
            ]);
        } catch (\Throwable) {
            // 审计失败不能影响业务；表未建时静默跳过
        }
    }
}