<?php
/**
 * 默认主题 - 分类页
 * @var array $category
 * @var array $posts
 * @var array $pager
 */
echo \Svel\Core\View::partial('list-body', [
    'heading'   => (string)$category['name'],
    'posts'     => $posts,
    'pager'     => $pager,
    'emptyText' => '这个分类下还没有文章。',
]);