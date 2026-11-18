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
CSS_VERSION = "20261118p"

NAV = [
    ("services", "Services", "/services/", [
        ("/services/#strategy", "AI strategy & readiness"),
        ("/services/#implementation", "Agentic AI implementation"),
        ("/services/#governance", "Run & govern"),
        ("/services/#enablement", "Team enablement"),
        ("/industries/", "Industries"),
        ("/about/", "About us"),
    ]),
    ("academy", "Academy", "https://academy.coralpetal.ai/", [
        ("https://academy.coralpetal.ai/", "Coral Petal AI Academy"),
    ]),
    ("insights", "Insights", "/insights/", []),
    ("careers", "Careers", "/join-us/", []),
]
# 2026-11-18 (Rohit): Industries + About us live under Services; Company menu became a plain Careers link.
SERVICES_PAGES = {"industries", "about"}
CHEVRON = '<svg class="chev" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5 6 7.5 9 4.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>'


def esc(t):
    return t.replace("&", "&amp;")


def nav_html(current):
    if current in SERVICES_PAGES:
        current = "services"
    items = []
    for key, label, href, subs in NAV:
        cur = ' aria-current="page"' if key == current else ""
        if not subs:
            items.append(f'          <li><a class="pill" href="{href}"{cur}>{esc(label)}</a></li>')
            continue
        links = "\n".join(f'                <li><a href="{h}">{esc(t)}</a></li>' for h, t in subs)
        items.append(
            f'          <li class="has-menu">\n'
            f'            <details>\n'
            f'              <summary class="pill"{cur}>{esc(label)} {CHEVRON}</summary>\n'
            f'              <ul class="submenu">\n{links}\n              </ul>\n'
            f'            </details>\n'
            f'          </li>')
    items.append('          <li><a class="btn btn-small" href="/contact/">Contact us</a></li>')
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
