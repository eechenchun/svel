<?php
/**
 * 默认主题 - 标签页
 * @var array $tag
 * @var array $posts
 * @var array $pager
 */
echo \Svel\Core\View::partial('list-body', [
    'heading'   => '标签：' . (string)$tag['name'],
    'posts'     => $posts,
    'pager'     => $pager,
    'emptyText' => '这个标签下还没有文章。',
]);