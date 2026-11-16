<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Flash.php
 * @desc 一次性提示：保存在 session，展示一次即清除，避免刷新重复出现
 */

namespace Svel\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        Csrf::startSession();
        $_SESSION['flash'] = ['type' => $type, 'msg' => $message];
    }

    /** @return array{type:string,msg:string}|null */
    public static function take(): ?array
    {
        Csrf::startSession();
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }
}