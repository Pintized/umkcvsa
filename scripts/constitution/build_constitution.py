# Regenerates the constitution body of public/about/constitution/index.html
# from the source .docx. Re-run this whenever a new revision is issued.
import io, re, zipfile
from xml.etree import ElementTree as ET

# Usage: python build_constitution.py <path-to-constitution.docx>
# Rewrites only the block between the CONSTITUTION:START/END markers in the
# page, so the surrounding template and styling are never touched.
import os
import sys

_here = os.path.dirname(os.path.abspath(__file__))
DOCX = sys.argv[1] if len(sys.argv) > 1 else os.path.join(_here, 'constitution.docx')
PAGE = os.path.normpath(os.path.join(
    _here, '..', '..', 'public', 'about', 'constitution', 'index.html'))
W = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'

ROMAN = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII','XIII','XIV','XV']


def paragraphs():
    root = ET.fromstring(zipfile.ZipFile(DOCX).read('word/document.xml'))
    out = []
    for p in root.iter(W + 'p'):
        txt = ''.join(t.text or '' for t in p.iter(W + 't')).strip()
        pPr = p.find(W + 'pPr')
        style, ilvl = '', None
        if pPr is not None:
            ps = pPr.find(W + 'pStyle')
            if ps is not None:
                style = ps.get(W + 'val', '')
            np = pPr.find(W + 'numPr')
            if np is not None:
                il = np.find(W + 'ilvl')
                ilvl = int(il.get(W + 'val')) if il is not None else 0
        if txt:
            out.append({'style': style, 'ilvl': ilvl, 'text': txt})
    return out


def esc(s):
    return (s.replace('&', '&amp;').replace('<', '&lt;')
             .replace('>', '&gt;').replace('"', '&quot;'))


def slug(s):
    s = re.sub(r'^(ARTICLE\s+[IVX]+)\s*:.*$', r'\1', s, flags=re.I)
    return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')


def classify(items):
    """Normalise the source's heading levels.

    The .docx has two authoring slips: 'Section G' is tagged as an Article, and
    Article XI's Sections B-E are plain paragraphs. Both are corrected here so
    the outline matches what the text itself says.
    """
    fixed = []
    for it in items:
        t, style = it['text'], it['style']
        lvl = None
        if style == 'Heading4':
            lvl = 'section' if t.lower().startswith('section ') else 'article'
        elif style == 'Heading5':
            lvl = 'section'
        elif style == 'Heading6':
            lvl = 'clause'
        elif it['ilvl'] is None and re.match(r'^Section [B-E]:', t):
            lvl = 'section'
        fixed.append({**it, 'lvl': lvl})
    return fixed


def render_list(items, i, depth=0):
    """Consume consecutive list paragraphs into nested <ol>s."""
    html = [f'<ol class="c-list lvl-{min(depth, 3)}">']
    while i < len(items):
        it = items[i]
        if it['ilvl'] is None or it['lvl']:
            break
        if it['ilvl'] < depth:
            break
        if it['ilvl'] > depth:
            inner, i = render_list(items, i, depth + 1)
            # nest inside the previous <li> rather than beside it
            if html[-1].endswith('</li>'):
                html[-1] = html[-1][:-len('</li>')] + inner + '</li>'
            else:
                html.append(inner)
            continue
        html.append(f'<li>{esc(it["text"])}</li>')
        i += 1
    html.append('</ol>')
    return ''.join(html), i


def build():
    items = classify(paragraphs())

    # drop the letterhead block and the standalone title line
    start = next(i for i, x in enumerate(items) if x['text'].startswith('Preamble'))
    revised = next((x['text'] for x in items if x['text'].startswith('Last Revised')), '')
    body = [x for x in items[start:] if not x['text'].startswith('Last Revised')]

    out, toc = [], []
    art_n = 0
    i = 0
    open_article = False

    while i < len(body):
        it = body[i]
        t = it['text']

        if it['lvl'] == 'article':
            if open_article:
                out.append('</div></article>')
            art_n += 1
            sid = slug(t)
            label, _, title = t.partition(':')
            num = ROMAN[art_n - 1] if art_n <= len(ROMAN) else str(art_n)
            is_preamble = t.lower().startswith('preamble')
            if is_preamble:
                art_n -= 1
                num = '§'
                label, title = 'Preamble', ''
            toc.append((sid, label.strip(), title.strip()))
            out.append(
                f'<article class="c-article reveal" id="{sid}">'
                f'<header class="c-article-head">'
                f'<span class="c-num">{esc(num)}</span>'
                f'<div><span class="c-kicker">{esc(label.strip())}</span>'
                f'{f"<h2>{esc(title.strip())}</h2>" if title.strip() else ""}</div>'
                f'</header><div class="c-article-body">')
            open_article = True
            i += 1
            continue

        if it['lvl'] == 'section':
            label, _, title = t.partition(':')
            if not title:
                label, _, title = t.partition('.')
            out.append(f'<h3 class="c-section"><span>{esc(label.strip())}</span>'
                       f'{esc(title.strip())}</h3>')
            i += 1
            continue

        if it['lvl'] == 'clause':
            label, _, title = t.partition(':')
            out.append(f'<h4 class="c-clause"><span>{esc(label.strip())}</span>'
                       f'{esc(title.strip())}</h4>')
            i += 1
            continue

        if it['ilvl'] is not None:
            frag, i = render_list(body, i, 0)
            out.append(frag)
            continue

        out.append(f'<p>{esc(t)}</p>')
        i += 1

    if open_article:
        out.append('</div></article>')

    toc_html = ''.join(
        f'<a href="#{sid}"><span>{esc(lbl)}</span>{esc(ttl)}</a>' for sid, lbl, ttl in toc)

    return ''.join(out), toc_html, revised, len(toc)


if __name__ == '__main__':
    body_html, toc_html, revised, n = build()
    page = io.open(PAGE, encoding='utf-8').read()
    block = (
        '<!-- CONSTITUTION:START (generated from the .docx by build_constitution.py) -->\n'
        '<section class="c-wrap">\n'
        '  <aside class="c-toc">\n'
        '    <div class="c-toc-inner">\n'
        '      <h2>Contents</h2>\n'
        f'      <nav>{toc_html}</nav>\n'
        '      <a class="btn ghost c-top" href="#top">Back to top</a>\n'
        '    </div>\n'
        '  </aside>\n'
        f'  <div class="c-doc">{body_html}</div>\n'
        '</section>\n'
        '<!-- CONSTITUTION:END -->')

    start = page.index('<!-- CONSTITUTION:START')
    end = page.index('<!-- CONSTITUTION:END -->') + len('<!-- CONSTITUTION:END -->')
    page = page[:start] + block + page[end:]
    date = re.sub(r'^Last Revised:\s*', '', revised).strip()
    page = re.sub(r'(<b class="c-revised">)[^<]*(</b>)',
                  lambda m: m.group(1) + esc(date) + m.group(2), page)
    io.open(PAGE, 'w', encoding='utf-8', newline='\n').write(page)
    print(f'wrote {n} articles, revision line: {revised!r}')
