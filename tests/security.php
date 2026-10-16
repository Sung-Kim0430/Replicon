<?php
/** 主题自行控制的输出与 URL 校验，不替代 Typecho 核心权限或 CSRF 集成测试。 */
define('__TYPECHO_ROOT_DIR__', dirname(__DIR__));
require dirname(__DIR__) . '/functions.php';

function checkSecurity($condition, $message)
{
    if (!$condition) throw new RuntimeException($message);
}

$payload = '"><img src=x onerror=alert(1)><script>alert(1)</script>';
$safe = repliconEscape($payload);
$dom = new DOMDocument();
$dom->loadHTML('<p>' . $safe . '</p><img alt="' . $safe . '" src="https://example.com/logo.png">');
checkSecurity($dom->getElementsByTagName('script')->length === 0, '文字不得创建 script');
checkSecurity($dom->getElementsByTagName('img')->length === 1, '属性不得创建额外标签');
checkSecurity(!$dom->getElementsByTagName('img')->item(0)->hasAttribute('onerror'), '属性不得注入事件处理器');
checkSecurity(repliconEscape('A &amp; B') === 'A &amp; B', '不应重复编码 Typecho 已编码实体');
checkSecurity(repliconEscape("\xFF") !== '', '无效 UTF-8 不应使整个字段变为空');
foreach (['javascript:alert(1)', 'data:image/svg+xml,<svg/>', 'file:///etc/passwd', '//example.com/x', 'https://example.com/" onerror="alert(1)'] as $url) {
    checkSecurity(repliconLogoUrl($url) === '', '拒绝无效或非 HTTP(S) 图片地址');
}
checkSecurity(repliconLogoUrl('https://example.com/logo.png?a=1&b=2') !== '', '合法 HTTPS 图片应保留');
checkSecurity(repliconLogoUrl('http://example.com/logo.png') !== '', '合法 HTTP 图片应保留');
echo "Output security regression checks passed.\n";
