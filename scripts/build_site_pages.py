#!/usr/bin/env python3
"""Build the main site's pages from one shared template.

  python3 scripts/build_site_pages.py

Layout lives in site-data/templates/ (page.html). Each page's content is one
file in site-data/pages/<slug>.html that starts with a metadata block:

  <!--
  path: services/          (output folder under site/, "" = home)
  nav: services            (which nav item is current)
  title: Page title
  description: Meta description
  -->
  ...body HTML...

Any change to the header, footer or <head> goes into the template, never into a
generated page. Then run this script and commit site/.
Standard library only.
"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "site-data"
SITE = ROOT / "site"
CSS_VERSION = "20261116a"

NAV = [
    ("services", "/services/", "Services"),
    ("industries", "/industries/", "Industries"),
    ("academy", "/academy/", "Academy"),
    ("about", "/about/", "About"),
    ("insights", "/insights/", "Insights"),
    ("careers", "/careers/", "Careers"),
]


def nav_html(current):
    items = []
    for key, href, label in NAV:
        cur = ' aria-current="page"' if key == current else ""
        items.append(f'          <li><a href="{href}"{cur}>{label}</a></li>')
    cur = ' aria-current="page"' if current == "contact" else ""
    items.append(f'          <li><a class="btn btn-small" href="/contact/"{cur}>Contact us</a></li>')
    return "\n".join(items)


def parse(page):
    text = page.read_text(encoding="utf-8")
    m = re.match(r"\s*<!--(.*?)-->\s*", text, re.S)
    if not m:
        raise SystemExit(f"{page}: missing metadata block")
    meta = {}
    for line in m.group(1).strip().splitlines():
        k, _, v = line.partition(":")
        meta[k.strip()] = v.strip()
    for k in ("path", "nav", "title", "description"):
        if k not in meta:
            raise SystemExit(f"{page}: metadata '{k}' missing")
    return meta, text[m.end():]


def main():
    tpl = (DATA / "templates" / "page.html").read_text(encoding="utf-8")
    built = []
    for page in sorted((DATA / "pages").glob("*.html")):
        meta, body = parse(page)
        path = meta["path"].strip("/")
        url = "https://coralpetal.ai/" + (path + "/" if path else "")
        html = (tpl.replace("{{title}}", meta["title"])
                   .replace("{{description}}", meta["description"])
                   .replace("{{canonical}}", url)
                   .replace("{{css_version}}", CSS_VERSION)
                   .replace("{{nav}}", nav_html(meta["nav"]))
                   .replace("{{body}}", body.rstrip() + "\n"))
        out = SITE / path / "index.html" if path else SITE / "index.html"
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(html, encoding="utf-8")
        built.append(url)
    sm = ['<?xml version="1.0" encoding="UTF-8"?>',
          '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">']
    sm += [f"  <url><loc>{u}</loc></url>" for u in sorted(built)]
    sm.append("</urlset>")
    (SITE / "sitemap.xml").write_text("\n".join(sm) + "\n", encoding="utf-8")
    print(f"built {len(built)} page(s)")


if __name__ == "__main__":
    main()
