<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Markdown.php
 * @desc Markdown 渲染子集：标题、段落、强调、代码、链接、图片、列表、引用、表格、分隔线
 *       不做完整 CommonMark；未支持的语法按纯文本输出，绝不报错
 */

namespace Svel\Core;

final class Markdown
{
    public static function toHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        return trim(self::blocks($lines));
    }

    /** @param array<int,string> $lines */
    private static function blocks(array $lines): string
    {
        $out = '';
        $n = count($lines);
        $i = 0;

        while ($i < $n) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;
                continue;
            }

            // 代码块
            if (preg_match('/^```([A-Za-z0-9_\-+]*)\s*$/', $line, $m)) {
                $lang = $m[1];
                $buf = [];
                $i++;
                while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) {
                    $buf[] = $lines[$i];
                    $i++;
                }
                $i++; // 跳过结束围栏
                $cls = $lang !== '' ? ' class="language-' . self::attr($lang) . '"' : '';
                $out .= '<pre><code' . $cls . '>' . self::code(implode("\n", $buf)) . "</code></pre>\n";
                continue;
            }

            // 标题
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $level = strlen($m[1]);
                $text = $m[2];
                $out .= '<h' . $level . ' id="' . self::attr(self::anchor($text)) . '">'
                    . self::inline($text) . '</h' . $level . ">\n";
                $i++;
                continue;
            }

            // 分隔线
            if (preg_match('/^\s{0,3}(-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
                $out .= "<hr>\n";
                $i++;
                continue;
            }

            // 引用
            if (preg_match('/^\s{0,3}>\s?/', $line)) {
                $buf = [];
                while ($i < $n && preg_match('/^\s{0,3}>\s?(.*)$/', $lines[$i], $m)) {
                    $buf[] = $m[1];
                    $i++;
                }
                $out .= "<blockquote>\n" . self::blocks($buf) . "</blockquote>\n";
                continue;
            }

            // 表格
            if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?[\s:\-|]+\|[\s:\-|]*$/', $lines[$i + 1])) {
                $table = self::table($lines, $i);
                if ($table !== null) {
                    $out .= $table;
                    continue;
                }
            }

            // 列表
            if (preg_match('/^\s{0,3}([-*+]|\d+\.)\s+/', $line)) {
                [$html, $i] = self::list($lines, $i);
                $out .= $html;
                continue;
            }

            // 段落
            $buf = [];
            while ($i < $n && trim($lines[$i]) !== '' && !self::isBlockStart($lines[$i], $i, $n, $lines)) {
                $buf[] = trim($lines[$i]);
                $i++;
            }
            if ($buf === []) {
                $buf[] = trim($lines[$i]);
                $i++;
            }
            $out .= '<p>' . self::inline(implode(' ', $buf)) . "</p>\n";
        }

        return $out;
    }

    private static function isBlockStart(string $line, int $i, int $n, array $lines): bool
    {
        if (preg_match('/^```/', $line)
            || preg_match('/^(#{1,6})\s+/', $line)
            || preg_match('/^\s{0,3}>\s?/', $line)
            || preg_match('/^\s{0,3}(-{3,}|\*{3,}|_{3,})\s*$/', $line)
            || preg_match('/^\s{0,3}([-*+]|\d+\.)\s+/', $line)
        ) {
            return true;
        }
        if (str_contains($line, '|') && $i + 1 < $n && preg_match('/^\s*\|?[\s:\-|]+\|[\s:\-|]*$/', $lines[$i + 1])) {
            return true;
        }
        return false;
    }

    /**
     * @param array<int,string> $lines
     * @return array{0:string,1:int}
     */
    private static function list(array $lines, int $i): array
    {
        $n = count($lines);
        $ordered = (bool)preg_match('/^\s{0,3}\d+\.\s+/', $lines[$i]);
        $tag = $ordered ? 'ol' : 'ul';
        $items = [];

        while ($i < $n && preg_match('/^\s{0,3}([-*+]|\d+\.)\s+(.*)$/', $lines[$i], $m)) {
            $item = $m[2];
            $i++;
            // 缩进的续行并入当前项
            while ($i < $n && preg_match('/^\s{2,}\S/', $lines[$i]) && !preg_match('/^\s{0,3}([-*+]|\d+\.)\s+/', $lines[$i])) {
                $item .= ' ' . trim($lines[$i]);
                $i++;
            }
            $items[] = $item;
        }

        $html = '<' . $tag . ">\n";
        foreach ($items as $item) {
            $html .= '<li>' . self::inline($item) . "</li>\n";
        }
        return [$html . '</' . $tag . ">\n", $i];
    }

    /**
     * @param array<int,string> $lines
     */
    private static function table(array $lines, int &$i): ?string
    {
        $header = self::cells($lines[$i]);
        if ($header === []) {
            return null;
        }
        $i += 2;
        $rows = [];
        $n = count($lines);
        while ($i < $n && str_contains($lines[$i], '|') && trim($lines[$i]) !== '') {
            $rows[] = self::cells($lines[$i]);
            $i++;
        }

        $html = "<div class=\"table-wrap\"><table>\n<thead><tr>";
        foreach ($header as $cell) {
            $html .= '<th>' . self::inline($cell) . '</th>';
        }
        $html .= "</tr></thead>\n<tbody>\n";
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($header as $idx => $_) {
                $html .= '<td>' . self::inline((string)($row[$idx] ?? '')) . '</td>';
            }
            $html .= "</tr>\n";
        }
        return $html . "</tbody></table></div>\n";
    }

    /** @return array<int,string> */
    private static function cells(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        if ($line === '') {
            return [];
        }
        return array_map('trim', explode('|', $line));
    }

    public static function inline(string $text): string
    {
        $codes = [];
        $text = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codes): string {
            $codes[] = '<code>' . htmlspecialchars($m[1], ENT_NOQUOTES, 'UTF-8') . '</code>';
            return "\x1A" . (count($codes) - 1) . "\x1A";
        }, $text) ?? $text;

        $text = htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

        // 图片
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', static function (array $m): string {
            $url = self::safeUrl($m[2]);
            if ($url === null) {
                return $m[0];
            }
            $title = isset($m[3]) && $m[3] !== '' ? ' title="' . self::attr($m[3]) . '"' : '';
            return '<img src="' . self::attr($url) . '" alt="' . self::attr($m[1]) . '"' . $title . ' loading="lazy" decoding="async">';
        }, $text) ?? $text;

        // 链接
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/', static function (array $m): string {
            $url = self::safeUrl($m[2]);
            if ($url === null) {
                return $m[1];
            }
            $title = isset($m[3]) && $m[3] !== '' ? ' title="' . self::attr($m[3]) . '"' : '';
            $rel = preg_match('#^https?://#i', $url) ? ' rel="noopener"' : '';
            return '<a href="' . self::attr($url) . '"' . $title . $rel . '>' . $m[1] . '</a>';
        }, $text) ?? $text;

        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text) ?? $text;

        return preg_replace_callback('/\x1A(\d+)\x1A/', static fn(array $m): string => $codes[(int)$m[1]] ?? '', $text) ?? $text;
    }

    private static function code(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
    }

    private static function attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 只放行 http(s)、站内绝对路径、锚点与相对路径 */
    public static function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, './')) {
            return $url;
        }
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $url)) {
            return null; // javascript:、data: 等一律拒绝
        }
        return $url;
    }

    public static function anchor(string $text): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? $text;
        $slug = trim($slug, '-');
        return $slug === '' ? 'h' : $slug;
    }

    /** 提取目录，供后台三栏编辑器右侧大纲使用 */
    public static function outline(string $markdown): array
    {
        $items = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m)) {
                $items[] = [
                    'level' => strlen($m[1]),
                    'text'  => $m[2],
                    'anchor' => self::anchor($m[2]),
                ];
            }
        }
        return $items;
    }
}