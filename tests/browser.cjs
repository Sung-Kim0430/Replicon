/*
 * 用真实 Chromium 执行模板、Prism 和间距脚本，并检查浏览器实际发出的资源请求。
 * HTTP 服务仅绑定本机临时端口，测试完成后关闭；不会访问线上博客或修改其数据。
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright-core');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const php = process.env.REPLICON_PHP || 'php';
const mime = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.png': 'image/png' };

(async () => {
    // 模拟被命名元素覆盖的 currentScript。补丁必须拒绝非 SCRIPT 元素，
    // 即使它包含看似合法的 src/hasAttribute，也不能被当成脚本自身的地址。
    const prismCore = fs.readFileSync(path.join(root, 'libs/prism/prism.js'), 'utf8')
        .split(/\r?\n/).find(line => line.startsWith('var _self='));
    const documentStub = {
        currentScript: { tagName: 'IMG', src: 'https://attacker.invalid/payload.js', hasAttribute: () => false },
        getElementsByTagName: () => []
    };
    const windowStub = { document: documentStub, Prism: { manual: true } };
    vm.runInNewContext(prismCore, { window: windowStub, document: documentStub });
    assert.equal(windowStub.Prism.util.currentScript(), null, 'Prism 必须拒绝伪装成 currentScript 的元素');

    const server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (['/', '/post', '/plain', '/below'].includes(url.pathname)) {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            const type = url.pathname === '/' ? 'index' : url.pathname.slice(1);
            // 原有资源调度断言固定为系统字体模式；另行验证本服务器的正文默认字体。
            const font = ['local', 'system', 'loli', 'google'].includes(url.searchParams.get('font')) ? url.searchParams.get('font') : 'system';
            res.end(execFileSync(php, [path.join(__dirname, 'fixtures/render.php'), type, font]));
            return;
        }
        const file = path.resolve(root, '.' + decodeURIComponent(url.pathname));
        if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
            res.writeHead(404).end();
            return;
        }
        res.setHeader('Content-Type', mime[path.extname(file)] || 'application/octet-stream');
        res.end(fs.readFileSync(file));
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    // 少数 Windows 主机把 10080 纳入临时端口池，Chromium 会禁止访问该端口。
    if (server.address().port === 10080) {
        await new Promise(resolve => server.close(resolve));
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    }
    let browser;
    try {
        const launch = process.env.REPLICON_BROWSER_PATH
            ? { executablePath: process.env.REPLICON_BROWSER_PATH }
            : { channel: 'chrome' };
        browser = await chromium.launch({ headless: true, ...launch });
        const page = await browser.newPage();
        const requests = [];
        const errors = [];
        page.on('request', req => requests.push(req.url()));
        page.on('pageerror', err => errors.push(err.message));
        const base = `http://127.0.0.1:${server.address().port}`;

        await page.goto(base, { waitUntil: 'networkidle' });
        assert(!requests.some(url => /prism|code\.bundle|woff2|fonts\.loli|googleapis/.test(url)), '首页不应请求高亮、代码字体或第三方字体');
        assert.equal(await page.locator('link[rel="stylesheet"]:not([media="print"])').count(), 1);

        requests.length = 0;
        await page.goto(base + '/plain', { waitUntil: 'networkidle' });
        assert(!requests.some(url => /prism\/prism\.js|webfonts\.css|woff2/.test(url)), '纯文字文章不应下载高亮脚本或代码字体');

        requests.length = 0;
        await page.goto(base + '/below', { waitUntil: 'networkidle' });
        assert(!requests.some(url => /webfonts\.css|woff2/.test(url)), '屏幕外代码不应提前下载字体');
        await Promise.all([
            page.waitForResponse(response => response.url().includes('webfonts.css')),
            page.locator('pre').scrollIntoViewIfNeeded()
        ]);
        await page.evaluate(() => document.fonts.ready);
        assert(requests.some(url => /woff2/.test(url)), '滚动接近代码后必须能加载字体');

        requests.length = 0;
        await page.goto(base + '/post', { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        assert.equal(await page.locator('#mixed img, #literal b').count(), 0, '已转义的文本不得被重新解析为 HTML');
        assert.equal(await page.locator('#literal').textContent(), '<b>ABC中文</b> & 结束');
        assert.equal(await page.locator('#mixed').textContent(), '中A文 <img src=x onerror=alert(1)> 与 B混排');
        assert.equal(await page.locator('#mixed .cjk-latin-auto-space').count(), 3, '相邻中西文边界不能因正则重叠而漏掉');
        assert.equal(await page.locator('#mixed .cjk-latin-custom-space').count(), 1);
        assert.equal(await page.locator('pre .cjk-latin-spacing, code .cjk-latin-spacing, #editor .cjk-latin-spacing, textarea .cjk-latin-spacing').count(), 0);
        assert.equal(await page.locator('#nested .cjk-latin-spacing .cjk-latin-spacing').count(), 0, '嵌套容器不得重复加工');
        assert(await page.locator('pre .token.keyword').count() > 0, '延迟加载后仍须正常高亮');
        assert.equal(await page.locator('.copy-to-clipboard-button').count(), 1, '复制工具栏必须保留');
        assert.equal(await page.locator('.line-numbers-rows').count(), 1, '行号插件必须保留');
        const codeText = await page.locator('pre code').textContent();
        await page.evaluate(() => { navigator.clipboard.writeText = async text => { window.copiedCode = text; }; });
        await page.locator('.copy-to-clipboard-button').click();
        assert.equal(await page.evaluate(() => window.copiedCode), codeText, '复制内容必须保留原始代码文字');
        const initialMarkup = await page.locator('main').innerHTML();
        await page.addScriptTag({ path: path.join(root, 'utils/cjk-latin-spacing/cjk-latin-spacing.js') });
        // addScriptTag 自身会添加 script；只比较正文区域以验证二次运行的幂等性。
        assert.equal(await page.locator('main').innerHTML(), initialMarkup);
        assert(!requests.some(url => /\.ttf(?:\?|$)|https:\/\//.test(url)), '默认模式不得请求 TTF 或外部资源');
        const fontRequests = requests.filter(url => url.includes('.woff2'));
        assert(fontRequests.length > 0 && fontRequests.length < 30, '简短代码仅应加载所需字体分片');
        const fontBytes = fontRequests.reduce((total, url) => total + fs.statSync(path.join(root, new URL(url).pathname)).size, 0);
        console.log(`Article font requests: ${fontRequests.length}; transferred font bytes: ${fontBytes}`);

        await page.emulateMedia({ reducedMotion: 'reduce' });
        assert.equal(await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior), 'auto');
        await page.setViewportSize({ width: 390, height: 844 });
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), '移动端页面不应横向溢出');
        assert.deepEqual(errors, [], '浏览器不应有脚本错误');

        // 模拟仍可选择的官方字体 CSS，避免测试依赖外部网络。
        await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '/* mocked font CSS */' }));
        await page.goto(base + '/plain?font=google', { waitUntil: 'networkidle' });
        assert.equal(await page.locator('link[data-replicon-font]').getAttribute('media'), 'all');

        requests.length = 0;
        await page.goto(base + '/plain?font=local', { waitUntil: 'networkidle' });
        await page.evaluate(() => document.fonts.ready);
        assert(requests.some(url => url.includes('/assets/css/serif.css')), '本服务器模式必须加载正文分片定义');
        assert(requests.some(url => url.includes('/assets/fonts/NotoSerifSC/') && url.includes('.woff2')), '正文分片必须实际从本服务器加载');
        assert(requests.every(url => new URL(url).origin === base), '本服务器模式不得连接 loli 或 Google');
        assert.equal(await page.locator('link[data-replicon-font]').getAttribute('media'), 'all');
        assert(await page.evaluate(() => [...document.fonts].some(face => face.family.includes('Replicon Noto Serif SC') && face.status === 'loaded')), '浏览器必须成功解析并使用自托管字体');
        const defaultHtml = execFileSync(php, [path.join(__dirname, 'fixtures/render.php'), 'plain'], { encoding: 'utf8' });
        assert(defaultHtml.includes('assets/css/serif.css'), '未保存字体选项时必须默认使用本服务器');
        requests.length = 0;
        await page.goto(base + '/plain?font=loli', { waitUntil: 'networkidle' });
        assert(requests.some(url => url.includes('/assets/css/serif.css')), '旧 loli 配置必须回到本服务器');
        assert(requests.every(url => new URL(url).origin === base), '停用 loli 后不得发起镜像请求');
        console.log('Browser regression checks passed.');
    } finally {
        if (browser) await browser.close();
        await new Promise(resolve => server.close(resolve));
    }
})().catch(err => { console.error(err); process.exitCode = 1; });
