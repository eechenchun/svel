<?php
declare(strict_types=1);

/**
 * SvelCMS - 超轻量 SEO 博客程序
 * @file app/Core/Editor.php
 * @desc 正文编辑区：内核只给纯文本框，编辑器由插件通过 admin_editor 钩子接管
 */

namespace Svel\Core;

final class Editor
{
    /** 默认编辑区：没有启用任何编辑器插件时就是它 */
    public static function render(array $post): string
    {
        $html = self::plain($post);
        return (string)Hook::filter('admin_editor', $html, $post);
    }

    /** 编辑区需要的额外资源，插件各自注入 */
    public static function assets(): string
    {
        $html = '';
        return (string)Hook::filter('admin_editor_assets', $html);
    }

    public static function plain(array $post): string
    {
        $content = (string)($post['content'] ?? '');
        $format = (string)($post['content_format'] ?? 'markdown');

        $out = '<div class="editor-plain">';
        $out .= '<div class="editor-bar">';
        $out .= '<label>内容格式 <select name="content_format">'
            . '<option value="markdown"' . ($format === 'markdown' ? ' selected' : '') . '>Markdown</option>'
            . '<option value="html"' . ($format === 'html' ? ' selected' : '') . '>HTML</option>'
            . '</select></label>';
        $out .= '<span class="editor-hint">未启用编辑器插件，直接编辑正文源码</span>';
        $out .= '</div>';
        $out .= '<textarea class="editor-textarea" name="content" id="content" rows="22" spellcheck="false">'
            . e($content) . '</textarea>';
        $out .= '</div>';

        return $out;
    }

    /** 后台编辑页当前的编辑器名称，用于提示 */
    public static function providerName(): string
    {
        $provider = \Svel\Models\Plugin::editorProvider();
        return $provider === null ? '纯文本框' : (string)$provider['name'];
    }
}