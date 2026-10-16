# 字体与首屏性能

## 当前代码的加载方式

- 全站公共样式合并为 `assets/css/theme.bundle.css`，保持原样式覆盖顺序。
- 文章与独立页面额外加载 `assets/css/code.bundle.css`；字体声明不包含在这张首屏样式表中。
- 打印样式使用 `media="print"`，不会作为屏幕渲染的阻塞样式。
- 两个主题脚本使用 `defer`。资源调度器只在页面有带语言标记的代码时下载 Prism。
- 代码出现在视口外 200px 范围内时，才加载 `webfonts.css`。旧浏览器不支持 IntersectionObserver 时立即加载。
- 首页列表和纯文字文章不请求代码字体；没有 JavaScript 时，代码仍可阅读，但使用系统字体且不执行语法高亮。
- 正文默认异步加载本服务器的 `serif.css` 和 Noto Serif SC WOFF2 分片，不连接外部字体服务器。后台可改选系统字体或 Google Fonts；下载期间继续使用系统字体。loli 配置已注释停用，旧的 loli 设置也会回到本服务器。

## 本地字体

正文字体 Noto Serif SC 存放在 `assets/fonts/NotoSerifSC/`，`serif.css` 只包含本服务器相对 URL。
保留官方的按字符分片及可变字重，常规和粗体共用对应的 WOFF2；来源、完整 SHA-256 校验值和 OFL 许可证随文件发布。
`tools/vendor_serif_font.py` 是开发时使用的下载工具；线上没有动态字体代理或服务器端请求转发。

原始 `LXGWWenKaiScreen.ttf` 为 18,910,612 字节。项目保留原文件用于构建，但网页不会再引用它。
中文字体按源字体实际 cmap 的全部 37,259 个字符生成 146 个 WOFF2 分片，每片声明准确的 `unicode-range`。
JetBrains Mono NL 的常规、斜体、粗体和粗斜体分别生成 WOFF2，准确匹配本机已安装的对应字体。

浏览器只请求代码中实际用到的分片，下载期间通过 `font-display: swap` 显示回退字体。
字体文件名包含 SHA-256 内容摘要的前 12 位，更新字体会改变 URL，适合长期缓存。
源码 TTF 和 OFL 授权文件必须保留；请勿只复制生成的字体而丢弃对应授权说明。

本地 Chromium 测试夹具中的短代码页请求了 3 个字体文件，共 229,072 字节。
这不是线上测速：不同文章字符、字体是否已安装、浏览器缓存、字体字重和网络条件都会改变请求量与耗时。
不能据此保证所有文章在某个固定时间内加载完成。

## 修改后的重建

调整 CSS 或替换 Prism 的 CSS 后：

```sh
python tools/build_assets.py
```

替换原始字体后，先在开发环境安装 `tools/requirements.txt`，再执行：

```sh
python -m pip install -r tools/requirements.txt
python tools/build_assets.py --fonts
```

重新生成的 CSS 与 WOFF2 应随主题一起发布。一般安装者无需 Python、Node.js 或线上构建步骤。
构建器不自动清理旧哈希字体；升级时可暂时保留旧文件，避免仍持有旧 CSS 的访问者出现字体 404。

## 部署端仍可优化的内容

仓库不包含生产 Caddy/Nginx、CDN 或 Typecho 实例配置，因此下面是待部署环境确认的项目。

1. 开启 HTML、CSS 和 JavaScript 的传输压缩。WOFF2 已经压缩，通常无需二次压缩。
2. 对带内容摘要的字体配置 `Cache-Control: public, max-age=31536000, immutable`。
3. CSS/JS 使用带版本参数的 URL。CDN 缓存键应保留查询参数，否则更新主题可能仍返回旧资源。
4. 使用 HTTP/2 或 HTTP/3，并比较目标访客地区的实际延迟。更换 CDN 或字体镜像之前先测量，不能只根据单次 VPN 对比推断根因。
5. 正文图片采用合适尺寸与 WebP/AVIF；首屏主图保持及时加载，其余图片使用 `loading="lazy"`、`decoding="async"` 并填写正确宽高。主题不会粗暴地给所有图片启用懒加载，避免推迟 LCP 主图。
6. 若 HTML 等待时间仍高，检查 Typecho 插件、数据库查询和页面缓存。登录态、评论表单和反垃圾令牌不宜直接套用不区分用户的公共 HTML 缓存。

Caddy 的示例片段如下，应合并到站点已有配置，并根据实际主题目录名调整匹配路径：

```caddyfile
encode zstd gzip

@versionedFonts path_regexp versionedFont /usr/themes/Replicon/assets/fonts/(web|NotoSerifSC)/[^/]+\.[0-9a-f]{12}\.woff2$
header @versionedFonts Cache-Control "public, max-age=31536000, immutable"
```

此示例仅设置压缩与字体缓存；不替代站点原有的 PHP 路由、HTTPS 和文件服务配置。
语法参考：[Caddy encode](https://caddyserver.com/docs/caddyfile/directives/encode) 与
[Caddy 请求匹配器](https://caddyserver.com/docs/caddyfile/matchers#path-regexp)。

## 如何复测

在浏览器开发者工具中分别测试首次访问与缓存命中、桌面与移动设备，记录 TTFB、FCP、LCP、CLS 以及字体传输量。
分别比较默认本服务器模式、系统字体模式与可选的 Google Fonts 模式；对相同页面保持一致的网络限速、缓存状态与设备条件。
本仓库的测试验证资源调度与功能，不模拟公网延迟，也不宣称已经完成生产环境 Lighthouse 审计。
