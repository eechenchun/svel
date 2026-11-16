<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Html.php
 * @desc 富文本白名单过滤：标签白名单 + 属性白名单 + 协议校验，事件属性与脚本一律清除
 */

namespace Svel\Core;

final class Html
{
    /** 标签 => 允许的属性 */
    private const ALLOWED = [
        'p'          => [],
        'br'         => [],
        'hr'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'del'        => [],
        's'          => [],
        'code'       => ['class'],
        'pre'        => ['class'],
        'blockquote' => [],
        'h1'         => ['id'],
        'h2'         => ['id'],
        'h3'         => ['id'],
        'h4'         => ['id'],
        'h5'         => ['id'],
        'h6'         => ['id'],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'a'          => ['href', 'title', 'rel', 'target'],
        'img'        => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'figure'     => [],
        'figcaption' => [],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => ['colspan', 'rowspan'],
        'td'         => ['colspan', 'rowspan'],
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="utf-8" ?><div id="svel-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$ok) {
            return self::plain($html);
        }

        $root = $doc->getElementById('svel-root');
        if ($root === null) {
            return self::plain($html);
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function walk(\DOMNode $node): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }
        // 先快照，遍历中会移动节点
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button'], true)) {
                $node->removeChild($child);
                continue;
            }

            if (!array_key_exists($tag, self::ALLOWED)) {
                self::walk($child);
                self::unwrap($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
            self::walk($child);
        }
    }

    private static function cleanAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        $remove = [];
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            if (!in_array($name, $allowed, true)) {
                $remove[] = $name;
                continue;
            }
            if ($name === 'href' || $name === 'src') {
                $safe = Markdown::safeUrl($attr->nodeValue ?? '');
                if ($safe === null) {
                    $remove[] = $name;
                }
            }
        }
        foreach ($remove as $name) {
            $el->removeAttribute($name);
        }

        if ($tag === 'a') {
            $href = $el->getAttribute('href');
            if ($href !== '' && preg_match('#^https?://#i', $href)) {
                $el->setAttribute('rel', 'noopener');
            }
            if ($el->getAttribute('target') !== '') {
                $el->setAttribute('target', '_blank');
            }
        }
        if ($tag === 'img') {
            if (!$el->hasAttribute('alt')) {
                $el->setAttribute('alt', '');
            }
            $el->setAttribute('loading', 'lazy');
            $el->setAttribute('decoding', 'async');
        }
    }

    private static function unwrap(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        if ($parent === null) {
            return;
        }
        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private static function plain(string $html): string
    {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}