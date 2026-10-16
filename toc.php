<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 一次解析正文，生成标题锚点并提取供目录使用的标题列表。
 * 保留作者设置的 ID；自动生成的 ID 避开全文所有现有锚点，包括非标题元素。
 * $addIds=false 仅提取标题，兼容 extractHeadings() 的原有接口。
 * 本函数不负责 HTML 清洗或访问权限，正文仍应由 Typecho 核心和插件处理。
 */
function prepareArticleHeadings($content, $addIds = true)
{
    $result = ['content' => $content, 'headings' => []];
    // 无标题时避免 DOM 解析，也避免无意义地重新序列化文章内容。
    if (!preg_match('/<h[1-6]\b/i', $content)) {
        return $result;
    }

    $dom = new DOMDocument();
    // 旧 libxml 对部分 HTML5 标签会给出提示；局部收集后恢复调用方的错误模式。
    // 使用 finally，防止异常改变后续插件的全局 XML 错误处理方式。
    $previousErrors = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
    }
    if (!$loaded) return $result;

    $xpath = new DOMXPath($dom);
    $usedIds = [];
    // 先遍历所有 ID，保证正文后方的固定锚点也不会被前面的自动标题占用。
    foreach ($xpath->query('//*[@id]') as $element) {
        $usedIds[$element->getAttribute('id')] = true;
    }

    foreach ($xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $node) {
        $text = trim($node->textContent);
        if ($text === '') continue;

        $id = $node->getAttribute('id');
        if ($addIds && $id === '') {
            $base = preg_replace('/\s+/u', '-', $text);
            $base = preg_replace('/[^\p{L}\p{N}-]+/u', '', $base);
            // 纯标点/表情标题清洗后可能为空，需要稳定的非空前缀再进行去重。
            if ($base === '') $base = 'section';
            $id = $base;
            $suffix = 2;
            while (isset($usedIds[$id])) {
                $id = $base . '-' . $suffix++;
            }
            $usedIds[$id] = true;
            $node->setAttribute('id', $id);
        }
        $result['headings'][] = [
            'level' => (int) substr($node->tagName, 1),
            'title' => $text,
            'id' => $id
        ];
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($addIds && $body) {
        $result['content'] = '';
        foreach ($body->childNodes as $child) {
            $result['content'] .= $dom->saveHTML($child);
        }
    }
    return $result;
}

function addIDsToHeadings($content)
{
    return prepareArticleHeadings($content)['content'];
}

function extractHeadings($content)
{
    return prepareArticleHeadings($content, false)['headings'];
}

/**
 * 渲染有效的嵌套列表，兼容 h2→h4→h1 等跳级情况。
 * 子列表必须位于 li 内；标题使用文本编码，ID 先进行 URL 片段编码再做属性编码。
 */
function generateTocByHeadings($headings)
{
    if (empty($headings)) return '';

    $index = 0;
    $count = count($headings);
    $renderList = function ($level, $root = false) use (&$renderList, &$index, $count, $headings) {
        $html = $root ? '<ul class="widget-list">' : '<ul>';
        while ($index < $count) {
            $heading = $headings[$index];
            if ($heading['level'] < $level) break;
            $index++;
            $html .= '<li class="toc-level-' . (int) $heading['level'] . '"><a href="#'
                . htmlspecialchars(rawurlencode($heading['id']), ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($heading['title'], ENT_QUOTES, 'UTF-8') . '</a>';
            if ($index < $count && $headings[$index]['level'] > $heading['level']) {
                $html .= $renderList($headings[$index]['level']);
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    };

    // 根列表允许全部合法级别，后面出现 h1 时不能提前结束整个目录。
    return $renderList(1, true);
}

function generateToc($modifiedContent)
{
    return generateTocByHeadings(extractHeadings($modifiedContent));
}
