/* 独立验证间距处理、转义文本与 Prism currentScript 安全补丁。 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { chromium } = require('playwright-core');
const root = path.resolve(__dirname, '..');

(async () => {
    // 非 SCRIPT 命名元素不能被误当作 Prism 自身的脚本地址。
    const prismCore = fs.readFileSync(path.join(root, 'libs/prism/prism.js'), 'utf8')
        .split(/\r?\n/).find(line => line.startsWith('var _self='));
    const documentStub = {
        currentScript: { tagName: 'IMG', src: 'https://attacker.invalid/payload.js', hasAttribute: () => false },
        getElementsByTagName: () => []
    };
    const windowStub = { document: documentStub, Prism: { manual: true } };
    vm.runInNewContext(prismCore, { window: windowStub, document: documentStub });
    assert.equal(windowStub.Prism.util.currentScript(), null);

    const launch = process.env.REPLICON_BROWSER_PATH
        ? { executablePath: process.env.REPLICON_BROWSER_PATH } : { channel: 'chrome' };
    const browser = await chromium.launch({ headless: true, ...launch });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', err => errors.push(err.message));
        await page.setContent('<main><p id="mixed">中A文 &lt;img src=x onerror=alert(1)&gt; 与 B混排</p>'
            + '<p id="literal">&lt;b&gt;ABC中文&lt;/b&gt; &amp; 结束</p>'
            + '<p><code>中文Code &lt;img src=x&gt;</code></p>'
            + '<p contenteditable="true" id="editor">中文Editable</p>'
            + '<ul><li id="nested"><a href="#">中文Link混排</a></li></ul>'
            + '<pre><code>const message = "中文Code";</code></pre>'
            + '<textarea>中文Input</textarea></main>');
        const script = path.join(root, 'utils/cjk-latin-spacing/cjk-latin-spacing.js');
        await page.addScriptTag({ path: script });
        assert.equal(await page.locator('#mixed img, #literal b').count(), 0);
        assert.equal(await page.locator('#literal').textContent(), '<b>ABC中文</b> & 结束');
        assert.equal(await page.locator('#mixed .cjk-latin-auto-space').count(), 3);
        assert.equal(await page.locator('#mixed .cjk-latin-custom-space').count(), 1);
        assert.equal(await page.locator('pre .cjk-latin-spacing, code .cjk-latin-spacing, #editor .cjk-latin-spacing, textarea .cjk-latin-spacing').count(), 0);
        assert.equal(await page.locator('#nested .cjk-latin-spacing .cjk-latin-spacing').count(), 0);
        const before = await page.locator('main').innerHTML();
        await page.addScriptTag({ path: script });
        assert.equal(await page.locator('main').innerHTML(), before);
        assert.deepEqual(errors, []);
        console.log('Browser security regression checks passed.');
    } finally {
        await browser.close();
    }
})().catch(err => { console.error(err); process.exitCode = 1; });
