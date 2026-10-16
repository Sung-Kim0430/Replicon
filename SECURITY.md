# 安全范围与维护

本仓库是 Typecho 主题，不包含 Typecho 核心、数据库、登录系统、生产服务器或插件配置。
安全检查以主题源码与本地渲染测试为范围，并参考 [OWASP Top 10:2025](https://owasp.org/Top10/2025/0x00_2025-Introduction/)
及 [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)。

## 已处理的路径

- **DOM XSS / CWE-79，OWASP A05:2025 Injection**：旧间距脚本把 DOM 中的纯文本重新交给 `innerHTML` 解析。当文本含中西文边界及已编码标签时，展示文字可能变为真实元素。现实现使用文本节点构造，且跳过代码、输入框及可编辑区域；浏览器测试包含已编码的标签与事件属性 payload。
- **依赖加固，OWASP A03:2025 Software Supply Chain Failures**：定制 Prism 1.29.0 已回移 CVE-2024-53382 的官方 `currentScript` 元素类型校验。详见 [补丁记录](libs/prism/REPLICON-PATCHES.md)。这不是整包升级，不能据此宣称所有 Prism 版本问题均已消除。本主题未打包 autoloader，默认也不调用异步 Worker 高亮，因此不将公告中的所有利用链直接等同于主题默认可利用漏洞。
- **配置输出加固**：版权、备案文字以及 LOGO 属性输出经过 HTML 编码，图片地址限定为 HTTP(S) 绝对 URL。配置字段由有权限的后台用户控制，这项加固不等于已经证明存在匿名可利用的存储型 XSS。
- **第三方依赖范围**：默认本服务器模式和系统字体模式不请求外部字体服务；Google Fonts 为后台显式选择的固定来源。loli 选项及来源已注释停用，旧配置回退到本服务器。自托管正文分片包含来源与 SHA-256 校验清单；下载工具只在开发时运行，不提供线上代理接口。主题资源调度器不从查询参数或文章文本读取脚本 URL。

## 不能由主题检查代替的内容

- 核心和插件的权限、CSRF、SQL 查询、上传处理、会话、密码哈希与安全日志。
- 实际部署的 Typecho/PHP 版本与补丁状态；仓库中的模板兼容代码不代表服务器版本已经验证。
- 生产 HTTPS、响应头、CSP、缓存、反向代理与跨域设置。

主题保留 Typecho 的 `$this->header()`、`$this->footer()`、评论提交接口和输出 API，不能因为模板中没有显式隐藏令牌就断言没有 CSRF 保护。
核心可能通过 header 钩子和后台反垃圾设置注入评论令牌；应在实际站点核对生成的表单与服务端校验。
正文与评论的 HTML 权限遵循 Typecho 核心和插件约定，主题不会把正文整体当作普通文本转义，也不新增一套替代核心的 HTML 清洗器。

## 本地回归检查

```sh
php tests/security.php
php tests/toc.php
npm ci
npm test
```

浏览器测试需要已安装的 Chrome、PHP 和 Node.js 20 以上版本，详见 README。
这些检查验证具体修复，不构成覆盖 OWASP 全部类别的渗透测试或无漏洞保证。
