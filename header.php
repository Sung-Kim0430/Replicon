<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<!DOCTYPE HTML>
<html>
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="renderer" content="webkit">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php $this->archiveTitle([
            'category' => _t('分类 %s 下的文章'),
            'search'   => _t('包含关键字 %s 的文章'),
            'tag'      => _t('标签 %s 下的文章'),
            'author'   => _t('%s 发布的文章')
        ], '', ' - '); ?><?php $this->options->title(); ?></title>

    <link rel="stylesheet" href="<?php repliconAssetUrl($this->options, 'assets/css/theme.bundle.css'); ?>">
    <?php // 列表页只展示标题，不下载代码高亮和代码字体；正文页保留完整功能。 ?>
    <?php if ($this->is('post') || $this->is('page')): ?>
        <link rel="stylesheet" href="<?php repliconAssetUrl($this->options, 'assets/css/code.bundle.css'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" media="print" href="<?php repliconAssetUrl($this->options, 'assets/css/print.css'); ?>">
    <script defer src="<?php repliconAssetUrl($this->options, 'utils/cjk-latin-spacing/cjk-latin-spacing.js'); ?>"></script>
    <script defer src="<?php repliconAssetUrl($this->options, 'assets/js/theme.js'); ?>"
        <?php if ($this->is('post') || $this->is('page')): ?>
            data-prism-src="<?php repliconAssetUrl($this->options, 'libs/prism/prism.js'); ?>"
            data-font-stylesheet="<?php repliconAssetUrl($this->options, 'assets/css/webfonts.css'); ?>"
        <?php endif; ?>></script>

    <?php
        $fontSources = [
            // 'loli' => ['https://fonts.loli.net', 'https://gstatic.loli.net'], // 已停用，不输出请求。
            'google' => ['https://fonts.googleapis.com', 'https://fonts.gstatic.com']
        ];
        // 尚未保存该配置的旧主题安装也默认走本服务器；保留用户显式选定的其他模式。
        $fontSource = $this->options->serifFontSource ?: 'local';
        // 旧数据库中已保存的停用来源或未知值统一回到本服务器，不能继续发起外部请求。
        if (!in_array($fontSource, ['local', 'system', 'google'], true)) {
            $fontSource = 'local';
        }
    ?>
    <?php if ($fontSource === 'local'): ?>
        <!-- 本服务器提供完整分片；异步启用，避免字体 CSS 阻塞文字首屏。 -->
        <link rel="stylesheet" media="print" data-replicon-font href="<?php repliconAssetUrl($this->options, 'assets/css/serif.css'); ?>">
        <noscript><link rel="stylesheet" href="<?php repliconAssetUrl($this->options, 'assets/css/serif.css'); ?>"></noscript>
    <?php endif; ?>
    <?php // 固定映射限制字体来源，避免配置被当成任意 URL 或 HTML 属性插入。 ?>
    <?php if (isset($fontSources[$fontSource])): ?>
        <?php list($fontCssHost, $fontFileHost) = $fontSources[$fontSource]; ?>
        <link rel="preconnect" href="<?php echo $fontCssHost; ?>">
        <link rel="preconnect" href="<?php echo $fontFileHost; ?>" crossorigin>
        <!-- 打印媒体先异步下载，加载完成才用于屏幕，避免第三方字体 CSS 阻塞首屏。 -->
        <link rel="stylesheet" media="print" data-replicon-font href="<?php echo $fontCssHost; ?>/css2?family=Noto+Serif+SC:wght@400;700&amp;display=swap">
        <noscript><link rel="stylesheet" href="<?php echo $fontCssHost; ?>/css2?family=Noto+Serif+SC:wght@400;700&amp;display=swap"></noscript>
    <?php endif; ?>

    <!-- 通过自有函数输出HTML头部信息 -->
    <?php $this->header(); ?>
</head>
<body>
<header id="header" class="clearfix">
    <div class="container">
        <div class="row">
            <div class="site-name col-mb-12 col-9">
                <?php $logoUrl = repliconLogoUrl($this->options->logoUrl); ?>
                <?php if ($logoUrl !== ''): ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>">
                        <img src="<?php echo repliconEscape($logoUrl); ?>" alt="<?php echo repliconEscape($this->options->title); ?>"/>
                    </a>
                <?php else: ?>
                    <a id="logo" href="<?php $this->options->siteUrl(); ?>"><?php $this->options->title() ?></a>
                    <p class="description"><?php $this->options->description() ?></p>
                <?php endif; ?>
            </div>
            <div class="site-search col-3 kit-hidden-tb">
                <form id="search" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
                    <label for="s" class="sr-only"><?php _e('搜索关键字'); ?></label>
                    <input type="text" id="s" name="s" class="text" placeholder="<?php _e('输入关键字搜索'); ?>"/>
                    <button type="submit" class="submit"><?php _e('搜索'); ?></button>
                </form>
            </div>
            <div class="col-mb-12">
                <nav id="nav-menu" class="clearfix" role="navigation">
                    <a<?php if ($this->is('index')): ?> class="current"<?php endif; ?>
                        href="<?php $this->options->siteUrl(); ?>"><?php _e('首页'); ?></a>
                    <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
                    <?php while ($pages->next()): ?>
                        <a<?php if ($this->is('page', $pages->slug)): ?> class="current"<?php endif; ?>
                            href="<?php $pages->permalink(); ?>"
                            title="<?php $pages->title(); ?>"><?php $pages->title(); ?></a>
                    <?php endwhile; ?>
                </nav>
            </div>
        </div><!-- end .row -->
    </div>
</header><!-- end #header -->
<div id="body">
    <div class="container">
        <div class="row">

    
    
