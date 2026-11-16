<?php
declare(strict_types=1);

/**
 * SvelCMS 插件：富文本编辑器
 * 产出 HTML，保存与输出都经过内核白名单过滤；与 Markdown 编辑器互斥使用
 */

use Svel\Core\Hook;

Hook::addFilter('admin_editor', static function (string $fallback, array $post): string {
    $content = (string)($post['content'] ?? '');
    $format = (string)($post['content_format'] ?? 'markdown');
    $showToolbar = (string)app_setting('richtext', 'show_toolbar', '1') === '1';
    $uploadUrl = url('admin/upload');
    $pastePlain = (string)app_setting('richtext', 'paste_plain', '1') === '1' ? '1' : '0';
    $minHeight = max(200, min(1200, (int)app_setting('richtext', 'min_height', 460)));

    // 内容不是 HTML 时按纯文本呈现，避免把 Markdown 源码直接当富文本编辑
    $html = $format === 'html' ? $content : '';

    $out = '<div class="rteditor" data-origin-format="' . e($format) . '" data-upload="' . e($uploadUrl) . '" data-token="' . e(\Svel\Core\Csrf::token()) . '" data-paste="' . $pastePlain . '">';
    if ($showToolbar) {
        $out .= '<div class="rteditor-bar">'
            . '<button type="button" data-rt="bold" title="加粗"><strong>B</strong></button>'
            . '<button type="button" data-rt="italic" title="斜体"><em>I</em></button>'
            . '<button type="button" data-rt="h2" title="二级标题">H2</button>'
            . '<button type="button" data-rt="h3" title="三级标题">H3</button>'
            . '<button type="button" data-rt="link" title="链接">链接</button>'
            . '<button type="button" data-rt="image" title="插入图片">图片</button>'
            . '<button type="button" data-rt="ul" title="无序列表">列表</button>'
            . '<button type="button" data-rt="quote" title="引用">引用</button>'
            . '<button type="button" data-rt="pre" title="代码">代码</button>'
            . '<button type="button" data-rt="clear" title="清除格式">清除</button>'
            . '</div>';
    }
    $out .= '<div class="rteditor-area" id="rteditor-area" style="min-height:' . $minHeight . 'px" contenteditable="true" role="textbox" aria-multiline="true" spellcheck="false">'
        . $html . '</div>';
    $out .= '<textarea class="rteditor-value" name="content" id="rteditor-value" hidden>' . e($content) . '</textarea>';
    $out .= '<div class="rteditor-foot">'
        . '<span>富文本编辑器 · 内容以 HTML 保存</span>'
        . '<span id="rteditor-count">0 字</span>'
        . '</div>';
    $out .= '<input type="hidden" name="content_format" value="html">';
    $out .= '</div>';

    return $out;
}, 30);

Hook::addFilter('admin_editor_assets', static function (string $html): string {
    return $html
        . '<link rel="stylesheet" href="' . e(asset_v('plugins/richtext/assets/editor.css')) . '">' . "\n"
        . '<script src="' . e(asset_v('plugins/richtext/assets/editor.js')) . '" defer></script>' . "\n";
}, 30);