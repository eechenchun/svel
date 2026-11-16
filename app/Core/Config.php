<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Config.php
 * @desc 配置加载与点号取值，config.php 只加载一次
 */

namespace Svel\Core;

final class Config
{
    private static array $data = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }
        if (!is_file($file)) {
            throw new \RuntimeException('配置文件不存在：' . $file);
        }
        $cfg = require $file;
        if (!is_array($cfg)) {
            throw new \RuntimeException('config.php 必须返回数组');
        }
        self::$data = $cfg;
        self::$loaded = true;
    }

    public static function all(): array
    {
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $ref = &self::$data;
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $ref[$part] = $value;
                break;
            }
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        unset($ref);
    }
}