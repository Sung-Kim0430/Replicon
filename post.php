<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $this->need('header.php'); ?>
<?php
    $this->need('toc.php');
    $showToc = !empty($this->options->sidebarBlock)
        && in_array('ShowTOC', $this->options->sidebarBlock);
    // 一次 DOM 解析取得正文和标题列表；关闭目录后仍保留可分享的标题锚点。
    $prepared = prepareArticleHeadings($this->content);
    $this->content = $prepared['content'];
    $this->toc = $showToc ? generateTocByHeadings($prepared['headings']) : '';
?>

<div class="col-mb-12 col-8" id="main" role="main">
    <article class="post" itemscope itemtype="http://schema.org/BlogPosting">
        <?php postMeta($this, 'post'); ?>
        <div class="post-content <?php if ($this->options->prismjsLineNumbers) echo "line-numbers";?>" itemprop="articleBody">
            <?php $this->content(); ?>
        </div>
    </article>

    <?php $this->need('comments.php'); ?>

</div><!-- end #main-->

<?php $this->need('sidebar.php'); ?>
<?php $this->need('footer.php'); ?>
