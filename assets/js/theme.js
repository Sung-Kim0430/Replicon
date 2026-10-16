/**
 * 主题资源调度器：正文 HTML 已解析后才决定是否加载可选的代码资源。
 * 资源地址来自模板中的固定本地路径，不读取 URL 查询、文章内容或用户表单。
 */
(() => {
    'use strict';
    const loader = document.currentScript;
    // 限定真实 SCRIPT 元素，避免旧浏览器命名元素覆盖 document.currentScript。
    if (!loader || loader.tagName !== 'SCRIPT') return;

    // 正文字体 CSS（本服务器或显式选定的镜像）使用非屏幕媒体异步下载。
    // 由外部脚本切换媒体，避免内联 onload，
    // 便于站点在允许主题脚本的前提下配置更严格的 CSP。
    for (const link of document.querySelectorAll('link[data-replicon-font]')) {
        const activate = () => { link.media = 'all'; };
        link.addEventListener('load', activate, { once: true });
        // 样式表可能已从缓存加载完成，不能只等一个已经发生过的 load 事件。
        if (link.sheet) activate();
    }

    // 页面没有带语言标记的代码时，Prism 的全部语法和插件都无需下载。
    // 动态脚本执行时 DOM 已就绪，Prism 自身会调度 highlightAll()。
    const highlightedCode = document.querySelector('code[class*="language-"], [class*="language-"] code, code[class*="lang-"], [class*="lang-"] code');
    if (highlightedCode && loader.dataset.prismSrc) {
        const script = document.createElement('script');
        script.src = loader.dataset.prismSrc;
        script.async = true;
        document.head.appendChild(script);
    }

    const code = document.querySelectorAll('pre, code');
    if (!code.length || !loader.dataset.fontStylesheet) return;
    let fontsLoaded = false;
    const loadFonts = () => {
        // 多个代码块可能在同一观察回调中进入视口，必须保证只插入一张样式表。
        if (fontsLoaded) return;
        fontsLoaded = true;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = loader.dataset.fontStylesheet;
        document.head.appendChild(link);
    };

    if (!('IntersectionObserver' in window)) {
        // 旧浏览器保留正常字体显示能力，代价是立即下载一次字体定义。
        loadFonts();
        return;
    }
    // 提前 200px 开始加载，兼顾滚动到代码时的显示与首屏网络资源竞争。
    const observer = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) {
            loadFonts();
            observer.disconnect();
        }
    }, { rootMargin: '200px' });
    code.forEach(element => observer.observe(element));
})();
