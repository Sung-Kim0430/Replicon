# Replicon 的 Prism 定制包

此目录保留 PrismJS **1.29.0** 的原有 Coy 样式、语言和插件组合。
`prism.js` 的 `Prism.util.currentScript()` 已回移 PrismJS 1.30.0 的官方安全修复：
仅在 `document.currentScript` 确实是 `SCRIPT` 元素时采用该值，防止命名元素造成 DOM Clobbering。

- 公告：[CVE-2024-53382 / GHSA-x7hr-w5r2-h6wg](https://github.com/advisories/GHSA-x7hr-w5r2-h6wg)
- 官方修复版本：[PrismJS v1.30.0](https://github.com/PrismJS/prism/releases/tag/v1.30.0)
- 上游参考：[v1.30.0 prism-core.js](https://github.com/PrismJS/prism/blob/v1.30.0/components/prism-core.js)

这是单项补丁，不代表整个定制包已经升级为 1.30.0；自动扫描器仍可能根据版本标记提示 1.29.0。
替换 Prism 文件时应采用包含上述修复的上游版本，并重新验证高亮、行号和复制插件。
更新 `prism.css` 后运行 `python tools/build_assets.py`，让部署用的代码样式包同步更新。
