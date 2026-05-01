#!/usr/bin/env python3
"""Stamp a static SvelteKit SPA shell with per-site SEO metadata.

The WCHS SPA is deployed as a static client-rendered app. Runtime SEO tags are
still emitted by Svelte after config loads, but crawlers and unfurlers need safe
metadata in the raw HTML too. This script runs during deploy for each site and
hydrates spa/build/index.html from the live /wp-json/wchs/v1/config payload.
"""

from __future__ import annotations

import argparse
import html
import json
import mimetypes
import re
from pathlib import Path
from urllib.parse import urljoin


START = "<!-- STATIC_SEO_START -->"
END = "<!-- STATIC_SEO_END -->"


def plain(value: object) -> str:
    text = "" if value is None else str(value)
    text = re.sub(r"<[^>]+>", " ", text)
    return re.sub(r"\s+", " ", html.unescape(text)).strip()


def esc(value: str) -> str:
    return html.escape(value, quote=True)


def absolute_url(value: object, origin: str) -> str:
    raw = plain(value)
    if not raw:
        return ""
    return urljoin(origin.rstrip("/") + "/", raw)


def icon_type(url: str) -> str:
    guessed, _ = mimetypes.guess_type(url)
    return guessed or "image/png"


def derive_payload(config: dict, domain: str) -> dict:
    origin = plain(config.get("spa_origin")) or plain(config.get("wp_origin"))
    if not origin:
        origin = f"https://{domain.strip('/')}"
    origin = origin.rstrip("/")

    brand = plain(config.get("brand_name")) or "Online Store"
    hero = ((config.get("homepage") or {}).get("hero") or {})
    description = (
        plain(config.get("static_seo_description"))
        or plain(hero.get("subheadline"))
        or plain(hero.get("headline"))
        or f"{brand} online store."
    )[:300]

    logo = (
        absolute_url(config.get("logo_full_url"), origin)
        or absolute_url(config.get("logo_url"), origin)
    )
    image = absolute_url(hero.get("image_desktop"), origin) or logo

    return {
        "origin": origin,
        "brand": brand,
        "title": plain(config.get("static_seo_title")) or brand,
        "description": description,
        "logo": logo,
        "image": image,
    }


def build_seo_block(payload: dict) -> str:
    origin = payload["origin"]
    title = payload["title"]
    description = payload["description"]
    image = payload["image"]
    logo = payload["logo"]
    site_name = payload["brand"]

    lines = [
        START,
        f'<title data-static-seo="title">{esc(title)}</title>',
        f'<meta data-static-seo="description" name="description" content="{esc(description)}" />',
        '<meta data-static-seo="robots" name="robots" content="index,follow" />',
        f'<link data-static-seo="canonical" rel="canonical" href="{esc(origin + "/")}" />',
        '<meta data-static-seo="og:type" property="og:type" content="website" />',
        f'<meta data-static-seo="og:title" property="og:title" content="{esc(title)}" />',
        f'<meta data-static-seo="og:description" property="og:description" content="{esc(description)}" />',
        f'<meta data-static-seo="og:url" property="og:url" content="{esc(origin + "/")}" />',
        f'<meta data-static-seo="og:site_name" property="og:site_name" content="{esc(site_name)}" />',
        '<meta data-static-seo="twitter:card" name="twitter:card" content="summary_large_image" />',
        f'<meta data-static-seo="twitter:title" name="twitter:title" content="{esc(title)}" />',
        f'<meta data-static-seo="twitter:description" name="twitter:description" content="{esc(description)}" />',
    ]

    if image:
        lines.extend([
            f'<meta data-static-seo="og:image" property="og:image" content="{esc(image)}" />',
            f'<meta data-static-seo="twitter:image" name="twitter:image" content="{esc(image)}" />',
        ])
    if logo:
        lines.append(f'<link data-static-seo="icon" rel="icon" href="{esc(logo)}" type="{esc(icon_type(logo))}" />')

    schema = {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": site_name,
        "url": origin + "/",
    }
    if logo:
        schema["logo"] = logo
    lines.append(
        '<script data-static-seo="schema" type="application/ld+json">'
        + json.dumps(schema, separators=(",", ":")).replace("<", "\\u003c")
        + "</script>"
    )
    lines.append(END)
    return "\n\t\t".join(lines)


def hydrate_index(index_path: Path, payload: dict) -> None:
    source = index_path.read_text(encoding="utf-8")
    block = build_seo_block(payload)
    pattern = re.compile(re.escape(START) + r".*?" + re.escape(END), re.DOTALL)
    if pattern.search(source):
        source = pattern.sub(block, source, count=1)
    else:
        source = source.replace("</head>", f"\t\t{block}\n\t</head>", 1)

    if payload["logo"]:
        icon = f'<link rel="icon" href="{esc(payload["logo"])}" type="{esc(icon_type(payload["logo"]))}" />'
        source = re.sub(r'\s*<link rel="icon" href="/favicon\.ico"[^>]*>\n?', "\n\t\t" + icon + "\n", source, count=1)
        source = re.sub(r'\s*<link rel="icon" type="image/png" href="/favicon\.png"[^>]*>\n?', "\n", source, count=1)

    index_path.write_text(source, encoding="utf-8")


def write_manifest(manifest_path: Path, payload: dict, accent: str) -> None:
    manifest = {
        "name": payload["brand"],
        "short_name": payload["brand"][:24],
        "start_url": "/",
        "display": "minimal-ui",
        "theme_color": accent or "#111111",
        "background_color": "#ffffff",
    }
    if payload["logo"]:
        manifest["icons"] = [
            {
                "src": payload["logo"],
                "sizes": "any",
                "type": icon_type(payload["logo"]),
            }
        ]
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--index", required=True, type=Path)
    parser.add_argument("--manifest", required=True, type=Path)
    parser.add_argument("--config", required=True, type=Path)
    parser.add_argument("--domain", required=True)
    args = parser.parse_args()

    config = json.loads(args.config.read_text(encoding="utf-8"))
    payload = derive_payload(config, args.domain)
    hydrate_index(args.index, payload)
    write_manifest(args.manifest, payload, plain(config.get("accent_color")))
    print(f"Hydrated SPA SEO shell: title={payload['title']!r} description={payload['description']!r}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
