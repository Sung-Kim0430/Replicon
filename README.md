# Replicon

Replicon 是基于 Typecho 默认主题 [Replica](https://github.com/typecho/typecho/tree/master/usr/themes/default) 开发的博客主题，注重简洁排版、中西文阅读与代码展示。

![主题示意](mockup.png)

## 安装

将完整主题目录放到 Typecho 的 `/usr/themes/Replicon/`，再到后台“外观”启用主题并调整设置。
发布文件中必须包含 `assets/css/*.bundle.css`、`assets/css/webfonts.css`、`assets/css/serif.css`、`assets/fonts/web/`、`assets/fonts/NotoSerifSC/` 和 `assets/js/theme.js`。
这些文件已包含在源码中，一般安装不需要运行构建，也不需要服务器安装 Node.js 或 Python。

文章目录功能使用 PHP DOM/libxml 扩展。若服务器缺少 DOM 扩展，需要先启用扩展。
仓库不包含完整 Typecho 实例；部署时仍需满足所用 Typecho 版本的运行要求。

## 字体与加载

正文默认从**本服务器**加载随主题发布的 Noto Serif SC WOFF2 分片，支持常规和粗体，不依赖访客连接 Google Fonts 或其他字体镜像。
字体异步加载，下载期间先显示本机衬线字体；没有 JavaScript 时使用普通字体样式表回退。
后台“正文衬线字体来源”可选择本服务器、系统字体或 Google Fonts；选择某个来源后不会同时下载其他来源。
loli 选项及来源配置已注释停用；旧的 loli 配置自动回退到本服务器，不会请求镜像。
已有站点若曾明确保存“系统字体”或 Google Fonts，需在后台改选“本服务器”；尚未设置该选项的站点自动使用本服务器。
本机回退依次尝试 Noto Serif SC、Source Han Serif SC、Songti SC、STSong、SimSun 和通用 serif。

代码字体优先使用本机对应字体，否则使用主题自托管 WOFF2：

- 西文：JetBrains Mono NL，提供常规、斜体、粗体和粗斜体。
- 中文：霞鹜文楷屏幕阅读版，按字符分片，覆盖源字体全部 37,259 个字符。
- 代码接近视口 200px 时才请求字体定义；纯文字页和首页列表不下载代码字体。

原 TTF 与 OFL 授权文件保留为构建源。网页不再直接加载完整 TTF，也不在 CSS 中使用外部字体 `@import`。
关于缓存、压缩、图片和首屏测速，见 [性能说明](docs/PERFORMANCE.md)。

## 阅读与排版

- 自动处理同一文本节点内的中西文间距：无空格约为 1/6 字宽，已有一个空格时约为 1/5 字宽。
- 脚本跳过代码、输入框、可编辑内容等区域，保持代码和用户输入原样；不跨独立 HTML 元素边界补空格。
- 文章页可在后台启用侧边栏目录。标题自动获得不冲突的 ID，目录链接支持中文标题、重复标题和标题级别跳转。
- 点击目录平滑滚动；系统启用“减少动态效果”时使用普通滚动。文章目录不在独立页面自动生成。
- 移动设备可以正常缩放页面。

## 代码高亮

主题使用 [Prism.js](https://prismjs.com/) 及 Coy 样式，并提供行号、工具栏、复制代码和 treeview 插件。
文章与独立页面中存在带 `language-*` 或 `lang-*` 标记的代码时，才下载高亮脚本。
代码块示例：

````markdown
```javascript
const message = "你好，Replicon";
console.log(message);
```
````

后台可设置文章代码块行号。当前定制包仍标记 Prism 1.29.0，已回移官方 CVE-2024-53382 安全修复，
具体范围与升级注意事项见 [Prism 补丁记录](libs/prism/REPLICON-PATCHES.md)。
如需更改语法或插件，请保留所需组件并选择包含该修复的上游版本。替换 `prism.css` 后还需重建样式包。

## 打印

在文章页面按 `Ctrl + P` 可以打印或保存 PDF：

- 隐藏导航、评论、侧边栏、版权与备案信息，保留主题的 Powered by 行。
- 取消代码块 500px 的高度限制，并隐藏工具栏及行号。
- 调整标题和正文间距；长代码行仍应由作者合理换行。

## 友链卡片

在支持 HTML 的页面内容中插入以下结构，将地址、头像、宽高和说明替换为实际内容。
头像建议使用合适的小尺寸图片，并保留真实宽高以减少布局跳动。

```html
<div class="friend-container">
    <a class="friend-card" href="https://inuebisu.cn">
        <img class="friend-avatar"
             src="https://archive.inuebisu.cn/avatar/kari_512.png"
             width="512" height="512" loading="lazy" decoding="async"
             alt="犬窝闲谭">
        <div class="friend-info">
            <p class="friend-name">犬窝闲谭</p>
            <p class="friend-description">犬戎的狗窝！</p>
        </div>
    </a>
</div>
```

上例中的尺寸应与实际头像一致；首屏主图不应照搬懒加载设置。

## 开发与验证

CSS 源文件仍分开维护；HTML 引用构建后的样式包，修改源样式后必须同步生成产物：

```sh
python tools/build_assets.py
```

只有更新原始字体时才需要安装字体构建依赖并重新生成 WOFF2：

```sh
python -m pip install -r tools/requirements.txt
python tools/build_assets.py --fonts
python tests/assets.py
```

自托管正文字体已随仓库发布。仅在需要重新获取官方分片时运行 `python tools/vendor_serif_font.py --refresh`；
该操作只在开发环境联网下载，生成 `serif.css`、WOFF2、来源校验清单和 OFL 许可证，不会在访客请求期间代理下载字体。

功能与安全回归：

```sh
php tests/toc.php
php tests/security.php
npm ci
npm test
```

浏览器测试使用 `playwright-core`，需要 Node.js 20 以上、PHP 与本机 Chrome。
PHP 不在 PATH 时设置环境变量 `REPLICON_PHP` 为可执行文件绝对路径；使用其他 Chromium 时设置 `REPLICON_BROWSER_PATH`。
测试使用本机临时 HTTP 服务和最小模板夹具，不连接生产博客，不替代完整 Typecho 集成或真实网络测速。

`.gitignore` 排除依赖目录、环境文件、编辑器文件、缓存和测试临时产物；部署必需的 CSS 与 WOFF2 刻意保留在版本库中。
性能说明与源码安全范围分别见 [性能文档](docs/PERFORMANCE.md) 和 [安全说明](SECURITY.md)。

## 示例与贡献

原项目示例站点为 [速效複寫](https://archive.inuebisu.cn)，线上实际版本与本地代码可能不同。
欢迎报告错误、提交功能请求和代码改进。反馈性能问题时，请注明页面、网络环境、缓存状态及浏览器。

## License

主题采用 [GNU General Public License v2.0](LICENSE.txt)。字体分别遵守对应目录中的 OFL 授权说明。
