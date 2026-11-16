<?php
declare(strict_types=1);

/**
 * SvelCMS 插件：Markdown 编辑器
 * 只负责后台编辑区 UI，正文渲染仍由内核 Markdown 类完成
 */

use Svel\Core\Csrf;
use Svel\Core\Hook;

Hook::addFilter('admin_editor', static function (string $fallback, array $post): string {
    $content = (string)($post['content'] ?? '');
    $previewUrl = url('admin/preview');
    $uploadUrl = url('admin/upload');
    $showToolbar = (string)app_setting('markdown', 'show_toolbar', '1') === '1';
    $showOutline = (string)app_setting('markdown', 'show_outline', '1') === '1';
    $tabSize = max(1, min(8, (int)app_setting('markdown', 'tab_size', 4)));
    $delay = max(0, min(2000, (int)app_setting('markdown', 'preview_delay', 300)));

    $rootClass = 'mdeditor' . ($showOutline ? '' : ' mdeditor-no-outline');
    $out = '<div class="' . $rootClass . '" data-preview="' . e($previewUrl) . '" data-upload="' . e($uploadUrl) . '" data-token="' . e(Csrf::token()) . '" data-delay="' . $delay . '" data-tab="' . $tabSize . '">';
    if ($showToolbar) {
        $out .= '<div class="mdeditor-bar">'
            . '<button type="button" data-md="bold" title="加粗">B</button>'
            . '<button type="button" data-md="italic" title="斜体"><em>I</em></button>'
            . '<button type="button" data-md="h2" title="二级标题">H2</button>'
            . '<button type="button" data-md="h3" title="三级标题">H3</button>'
            . '<button type="button" data-md="link" title="链接">链接</button>'
            . '<button type="button" data-md="image" title="图片">图片</button>'
            . '<button type="button" data-md="quote" title="引用">引用</button>'
            . '<button type="button" data-md="code" title="代码块">代码</button>'
            . '<button type="button" data-md="ul" title="无序列表">列表</button>'
            . '<button type="button" data-md="ol" title="有序列表">1.</button>'
            . '<button type="button" data-md="hr" title="分隔线">—</button>'
            . '<span class="mdeditor-bar-gap"></span>'
            . '<button type="button" data-md="refresh" title="刷新预览">刷新预览</button>'
            . '</div>';
    }
    $out .= '<div class="mdeditor-body">';
    $out .= '<div class="mdeditor-pane mdeditor-source">'
        . '<textarea id="mdeditor-input" name="content" spellcheck="false" aria-label="Markdown 源码">' . e($content) . '</textarea>'
        . '</div>';
    $out .= '<div class="mdeditor-pane mdeditor-preview" id="mdeditor-preview"><p class="mdeditor-placeholder">预览区</p></div>';
    if ($showOutline) {
        $out .= '<aside class="mdeditor-pane mdeditor-outline"><h4>目录</h4><ul id="mdeditor-outline"></ul></aside>';
    }

    $out .= '</div>';
    $out .= '<div class="mdeditor-foot"><span id="mdeditor-count">0 字</span><span>Markdown 编辑器 · 源码与预览滚动同步</span></div>';
    $out .= '<input type="hidden" name="content_format" value="markdown">';
    $out .= '</div>';

    return $out;
}, 20);

Hook::addFilter('admin_editor_assets', static function (string $html): string {
    return $html
        . '<link rel="stylesheet" href="' . e(asset_v('plugins/markdown/assets/editor.css')) . '">' . "\n"
        . '<script src="' . e(asset_v('plugins/markdown/assets/editor.js')) . '" defer></script>' . "\n";
}, 20);