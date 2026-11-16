<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Slug.php
 * @desc slug 生成与校验：ASCII 标题自动转换，中文标题不做拼音（保持超轻量），交给调用方补 ID
 */

namespace Svel\Core;

final class Slug
{
    /** 与路由冲突的保留字 */
    public const RESERVED = [
        'admin', 'post', 'page', 'category', 'tag', 'search', 'feed',
        'sitemap', 'robots', 'index', 'install', 'static', 'app', 'storage',
    ];

    /** 把标题转成合法 slug，返回空串表示标题里没有可用 ASCII 字母 */
    public static function ascii(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = preg_replace('/-{2,}/', '-', $text) ?? $text;
        if (!preg_match('/[a-z]/', $text)) {
            return '';
        }
        return mb_substr($text, 0, 191, 'UTF-8');
    }

    /** 生成可用 slug；中文标题返回前缀 + 随机串，后台可随时手改 */
    public static function make(string $text, string $prefix = 'item'): string
    {
        $slug = self::ascii($text);
        if ($slug === '') {
            $slug = $prefix . '-' . bin2hex(random_bytes(3));
        }
        return self::ensureNotReserved($slug);
    }

    /** 用自增 ID 作为兜底 slug，保证中文文章地址稳定可读 */
    public static function withId(string $prefix, int $id): string
    {
        return $prefix . '-' . $id;
    }

    public static function ensureNotReserved(string $slug): string
    {
        return in_array(strtolower($slug), self::RESERVED, true) ? $slug . '-1' : $slug;
    }

    public static function valid(string $slug): bool
    {
        return $slug !== ''
            && mb_strlen($slug, 'UTF-8') <= 191
            && preg_match('/^[a-z0-9\-]+$/', $slug) === 1
            && !in_array($slug, self::RESERVED, true);
    }
}