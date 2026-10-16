"""验证 CSS 本地资源路径、分片字符覆盖和内容摘要，需要 tools/requirements.txt。"""
import hashlib
import re
from pathlib import Path
from fontTools.ttLib import TTFont

root = Path(__file__).resolve().parents[1]
for name in ["theme.bundle.css", "code.bundle.css", "webfonts.css", "serif.css"]:
    css = root / "assets/css" / name
    for url in re.findall(r'url\([\"\']?([^\"\')]+)', css.read_text(encoding="utf-8")):
        if not re.match(r"(?:[a-z]+:|/|#)", url, re.I):
            assert (css.parent / url).is_file(), f"Missing CSS resource: {url}"

source = root / "assets/fonts/LXGWWenKaiScreen/LXGWWenKaiScreen.ttf"
with TTFont(source) as font:
    expected = set(font.getBestCmap())
covered = set()
for path in (root / "assets/fonts/web").glob("*.woff2"):
    digest = hashlib.sha256(path.read_bytes()).hexdigest()[:12]
    assert path.name.split(".")[-2] == digest, f"Invalid font content hash: {path.name}"
    if path.name.startswith("LXGWWenKaiScreen-"):
        with TTFont(path) as font:
            points = set(font.getBestCmap())
        covered.update(points)
assert covered == expected, f"Lost or extra characters: {len(expected ^ covered)}"

# OpenType 布局闭包可能保留镜像符号的额外 cmap，这是合法的字体依赖。
# 浏览器真正的分片选择由 CSS unicode-range 决定，因此验证 CSS 声明无交集，
# 并且每个声明的字符确实存在于对应字体，而不是禁止底层 cmap 的合法闭包。
declared = set()
css = (root / "assets/css/webfonts.css").read_text(encoding="utf-8")
for rule in re.findall(r"@font-face\s*\{([^}]+)\}", css):
    match = re.search(r"unicode-range:\s*([^;]+)", rule)
    if not match:
        continue
    points = set()
    for value in match.group(1).split(','):
        bounds = value.strip()[2:].split('-')
        points.update(range(int(bounds[0], 16), int(bounds[-1], 16) + 1))
    assert not (declared & points), 'CSS font ranges must not overlap'
    filename = re.search(r'url\("([^\"]+)"\)', rule).group(1)
    with TTFont(root / 'assets/css' / filename) as font:
        assert points <= set(font.getBestCmap()), 'CSS range advertises missing glyphs'
    declared.update(points)
assert declared == expected, 'CSS ranges must cover the complete original cmap'
print(f"Asset checks passed; all {len(covered)} Chinese font characters preserved.")

# 正文自托管字体保留官方下载清单，验证内容摘要及字重轴，防止下载错误页或误合并静态字体。
import json
manifest = json.loads((root / 'assets/fonts/NotoSerifSC/SOURCE.json').read_text(encoding='utf-8'))
for entry in manifest['files'].values():
    path = root / 'assets/fonts/NotoSerifSC' / entry['file']
    assert hashlib.sha256(path.read_bytes()).hexdigest() == entry['sha256'], f'Invalid serif font hash: {path.name}'
    with TTFont(path) as font:
        weight = next(axis for axis in font['fvar'].axes if axis.axisTag == 'wght')
        assert weight.minValue <= 400 and weight.maxValue >= 700, 'Serif font must cover both requested weights'
serif_css = (root / 'assets/css/serif.css').read_text(encoding='utf-8')
assert not re.search(r'url\(https?://', serif_css), 'Self-hosted serif CSS must not request external fonts'
print(f"Self-hosted serif checks passed; {len(manifest['files'])} WOFF2 files verified.")
