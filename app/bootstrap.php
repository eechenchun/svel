<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/bootstrap.php
 * @desc 常量、autoload、错误处理。任何入口文件只允许 require 这一处
 */

use Svel\Core\Log;

define('SVEL_ROOT', dirname(__DIR__));
define('SVEL_APP', SVEL_ROOT . '/app');
define('SVEL_VERSION', '1.2.2');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Svel\\')) {
        return;
    }
    $file = SVEL_APP . '/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require SVEL_APP . '/helpers.php';

set_error_handler(static function (int $no, string $message, string $file = '', int $line = 0): bool {
    if (!(error_reporting() & $no)) {
        return false;
    }
    if (in_array($no, [E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING], true)) {
        Log::warn($message, $file, $line);
        return false;
    }
    throw new ErrorException($message, 0, $no, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    Log::error($error['message'], $error['file'], (int)$error['line']);
});