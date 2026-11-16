<?php
/**
 * 默认主题 - 搜索结果
 * @var string $q
 * @var array $posts
 * @var int $total
 */
$heading = $q === '' ? '搜索' : '搜索：' . $q;
$empty = $q === '' ? '输入关键词开始搜索。' : '没有找到相关内容，换个词试试。';
echo \Svel\Core\View::partial('list-body', [
    'heading'   => $heading,
    'posts'     => $posts,
    'pager'     => [],
    'emptyText' => $empty,
]);