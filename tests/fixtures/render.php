<?php
/**
 * 最小 Typecho 模板夹具：只实现本主题用到的渲染接口。
 * 它验证模板与资源加载行为，不替代真实 Typecho 的鉴权、数据库或评论集成测试。
 */
namespace Widget\Contents\Page {
    class Rows
    {
        public static function alloc() { return new self(); }
        public function to(&$value) { $value = $this; }
        public function next() { return false; }
    }
}

namespace {
    define('__TYPECHO_ROOT_DIR__', dirname(__DIR__, 2));
    require __TYPECHO_ROOT_DIR__ . '/functions.php';
    function _t($text) { return $text; }
    function _e($text) { echo $text; }

    class FixtureOptions
    {
        public $serifFontSource = null;
        public $logoUrl = null;
        public $copyrightInfo = null;
        public $beianInfo = null;
        public $title = 'Replicon 测试';
        public function charset() { echo 'UTF-8'; }
        public function themeUrl($path) { echo '/' . $path; }
        public function siteUrl() { echo '/'; }
        public function title() { echo 'Replicon 测试'; }
        public function description() { echo '中西文排版与代码测试'; }
    }

    class FixturePage
    {
        public $options;
        private $type;
        public function __construct($type) { $this->type = $type; $this->options = new FixtureOptions(); }
        public function is($type) { return $this->type === $type || ($type === 'post' && in_array($this->type, ['plain', 'below'])); }
        public function archiveTitle($formats, $prefix, $suffix) {}
        public function header() {}
        public function footer() {}
        public function render() {
            require __TYPECHO_ROOT_DIR__ . '/header.php';
            echo '<main id="main" class="col-mb-12 col-8"><article class="post"><h1>测试Heading</h1>';
            if ($this->type !== 'index') {
                if ($this->type === 'below') echo '<div style="height:2500px">首屏纯文字</div>';
                if ($this->type !== 'plain') {
                echo '<div class="post-content line-numbers"><p id="mixed">中A文 &lt;img src=x onerror=alert(1)&gt; 与 B混排</p>'
                    . '<p id="literal">&lt;b&gt;ABC中文&lt;/b&gt; &amp; 结束</p>'
                    . '<p id="inline"><code>中文Code &lt;img src=x&gt;</code></p>'
                    . '<p contenteditable="true" id="editor">中文Editable</p>'
                    . '<ul><li id="nested"><a href="#">中文Link混排</a></li></ul>'
                    . '<pre><code class="language-javascript">const message = "中文Code";' . "\n" . 'console.log(message);</code></pre>'
                    . '<textarea id="input">中文Input</textarea></div>';
                } else {
                    echo '<p>这是一篇纯文字文章，没有代码。</p>';
                }
            }
            echo '</article></main>';
            require __TYPECHO_ROOT_DIR__ . '/footer.php';
        }
    }
    $fixture = new FixturePage($argv[1] ?? 'post');
    $fixture->options->serifFontSource = $argv[2] ?? null;
    $fixture->render();
}
