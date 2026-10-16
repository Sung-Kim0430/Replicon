(() => {
    'use strict';

    const containers = 'p, h1, h2, h3, h4, h5, h6, a, li, blockquote, table';
    // 代码、输入区域与可编辑内容必须保持原样，避免破坏复制或用户正在编辑的文本。
    // 自己生成的包装节点也整棵跳过，让脚本重复执行时保持幂等。
    const excluded = 'script, style, noscript, pre, code, textarea, input, select, option, template, svg, math, [contenteditable]:not([contenteditable="false"]), .cjk-latin-spacing';
    const boundary = /([\u4e00-\u9fff])([A-Za-z0-9])|([A-Za-z0-9])([\u4e00-\u9fff])|([\u4e00-\u9fff]) ([A-Za-z0-9])|([A-Za-z0-9]) ([\u4e00-\u9fff])/g;

    function applySpacing() {
        const walker = document.createTreeWalker(document.body,
            NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
                acceptNode(node) {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        return node.matches(excluded) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_SKIP;
                    }
                    return node.parentElement.closest(containers)
                        ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                }
            });

        // 只遍历正文树一次；先收集再替换，避免游标进入新节点造成重复处理。
        // 不再分别递归 p、a、li 等嵌套容器，避免对同一文本重复加工。
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        for (const node of nodes) {
            const text = node.nodeValue;
            boundary.lastIndex = 0;
            if (!boundary.test(text)) continue;
            boundary.lastIndex = 0;
            // 始终使用文本节点；禁止将文本交给 innerHTML 重新解释。
            // 文章中展示的 &lt;img onerror=...&gt; 必须一直是文字，不能变成可执行标签。
            const wrapper = document.createElement('span');
            wrapper.className = 'cjk-latin-spacing';
            let cursor = 0;
            let match;
            while ((match = boundary.exec(text))) {
                const left = match[1] || match[3] || match[5] || match[7];
                const right = match[2] || match[4] || match[6] || match[8];
                const hasSpace = Boolean(match[5] || match[7]);
                wrapper.appendChild(document.createTextNode(text.slice(cursor, match.index) + left));
                const space = document.createElement('span');
                space.className = hasSpace ? 'cjk-latin-custom-space' : 'cjk-latin-auto-space';
                if (hasSpace) space.textContent = ' ';
                wrapper.appendChild(space);
                // 让右字符参与下一次匹配，否则“中A文”会漏掉 A 与“文”之间的间距。
                cursor = match.index + match[0].length - right.length;
                boundary.lastIndex = cursor;
            }
            wrapper.appendChild(document.createTextNode(text.slice(cursor)));
            node.replaceWith(wrapper);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySpacing, { once: true });
    } else {
        applySpacing();
    }
})();
