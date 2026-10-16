<?php
/**
 * 无需安装 Typecho 或数据库即可验证目录处理的边界情况。
 * 使用显式异常而不是 assert()，避免 PHP 生产配置关闭断言后悄悄跳过测试。
 */
define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
require dirname(__DIR__) . '/toc.php';

function check($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

$content = '<div id="重复"></div><h2>重复</h2><h4>子级</h4>'
    . '<h1>起点</h1><h6>末级</h6><h2>重复</h2><h2 id="重复-2">固定</h2>'
    . '<h3>!!!</h3><h3>0</h3><h3>&lt;img src=x onerror=alert(1)&gt;</h3>';
$previous = libxml_use_internal_errors(false);
$prepared = prepareArticleHeadings($content);
check(libxml_use_internal_errors() === false, '必须恢复调用方的 libxml 错误模式');
libxml_use_internal_errors($previous);

$ids = array_column($prepared['headings'], 'id');
check(count($ids) === count(array_unique($ids)), '生成的标题 ID 必须唯一');
check($ids[0] === '重复-3', '必须预留后方标题和非标题元素已有的 ID');
check($ids[4] === '重复-4' && $ids[5] === '重复-2', '已有 ID 必须保留');
check($ids[6] === 'section', '纯标点标题需要非空的回退 ID');
check($prepared['headings'][7]['title'] === '0', '数字零不是空标题');

$toc = generateTocByHeadings($prepared['headings']);
$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="utf-8" ?>' . $toc);
$xpath = new DOMXPath($dom);
check($xpath->query('//ul/ul')->length === 0, '嵌套列表必须位于 li 内');
check($xpath->query('//a')->length === count($ids), '跳级及返回较浅标题不能丢失条目');
check($xpath->query('//img')->length === 0, '标题中的 HTML 必须作为文本输出');
foreach ($xpath->query('//a') as $index => $link) {
    check(rawurldecode(substr($link->getAttribute('href'), 1)) === $ids[$index], '目录锚点必须准确对应标题');
}
check(prepareArticleHeadings('')['content'] === '', '空文章应原样返回');
$plain = '<p>没有标题 &amp; 保持原样</p>';
check(prepareArticleHeadings($plain)['content'] === $plain, '无标题文章不应解析重写');
check(generateToc('') === '', '空文章不生成目录');
check(addIDsToHeadings($prepared['content']) === $prepared['content'], '处理两次不应改变已有 ID');
echo "TOC regression checks passed.\n";
