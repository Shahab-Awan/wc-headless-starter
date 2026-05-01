#!/usr/bin/env python3
"""
Gemini 2.5 Pro visual critique for the wc-headless-starter.

Takes a set of screenshot targets (label + image path + focused prompt),
sends each to Gemini, saves the critique as JSON for inspection.

Usage:
    python3 tests/gemini-critique.py

Outputs:
    tests/critique/<label>.json      — Gemini's structured response
    tests/critique/_summary.md       — Consolidated human-readable summary
"""

from __future__ import annotations

import base64
import json
import os
import sys
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parent.parent
SHOTS = ROOT / "tests" / "screenshots" / "responsive"
OUT = ROOT / "tests" / "critique"
OUT.mkdir(parents=True, exist_ok=True)

API_KEY_PATH = Path.home() / ".config" / "gemini" / "api-key"
MODEL = "gemini-2.5-pro"


def load_api_key() -> str:
    if not API_KEY_PATH.exists():
        raise SystemExit(f"Gemini API key not found at {API_KEY_PATH}")
    return API_KEY_PATH.read_text().strip()


def analyze(image_path: Path, prompt: str, api_key: str) -> dict:
    """Send a single image + prompt to Gemini 2.5 Pro."""
    img_bytes = image_path.read_bytes()
    b64 = base64.b64encode(img_bytes).decode("ascii")

    url = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent?key={api_key}"
    payload = {
        "contents": [
            {
                "parts": [
                    {"text": prompt},
                    {"inline_data": {"mime_type": "image/png", "data": b64}},
                ]
            }
        ],
        "generationConfig": {
            "temperature": 0.1,
            "maxOutputTokens": 8000,
            "responseMimeType": "application/json",
        },
    }

    r = requests.post(url, json=payload, timeout=180)
    r.raise_for_status()
    data = r.json()

    text = (
        data.get("candidates", [{}])[0]
        .get("content", {})
        .get("parts", [{}])[0]
        .get("text", "")
        .strip()
    )
    if text.startswith("```"):
        lines = text.split("\n")
        if lines[0].startswith("```"):
            lines = lines[1:]
        if lines and lines[-1].startswith("```"):
            lines = lines[:-1]
        text = "\n".join(lines).strip()

    try:
        return json.loads(text)
    except json.JSONDecodeError:
        return {"_raw": text, "_parse_error": True}


# ---------------------------------------------------------------------------
# Targets — the actual things we want Gemini to critique.
# ---------------------------------------------------------------------------

PROMPT_SPA_DESKTOP_HOME = """You are a senior front-end designer doing a merciless critique of this website homepage screenshot. The target aesthetic is Runway-style (runwayml.com) — cinematic, tight editorial typography, zero-shadow restraint, one typeface (Inter) throughout.

**IMPORTANT**: This site supports BOTH light and dark themes as first-class. Do NOT penalize a light-theme screenshot for "not being dark" — evaluate each theme on its own Runway-aligned merits. Light mode should still feel editorial, cinematic within its constraints, and tight.

Score each element on a 1-10 scale and explain WHY. Be brutally honest. No rubber-stamping.

Return JSON in exactly this shape:

{
  "overall_score": <1-10>,
  "overall_verdict": "<one sentence>",
  "hero": {
    "score": <1-10>,
    "issues": ["issue 1", "issue 2", ...],
    "what_works": ["good thing 1", ...],
    "typography_notes": "<what's off about the type>",
    "webgl_backdrop_visible": <true|false>,
    "webgl_coverage": "<describe where the webgl backdrop is visible, if at all — is it covering the full hero, only the corner, not visible, etc.>",
    "webgl_cropped": <true|false>,
    "webgl_cropping_note": "<if cropped, describe WHERE the cropping boundary is — above the hero text, to the left, etc.>"
  },
  "header": {
    "score": <1-10>,
    "issues": [...],
    "brand_wrapping": <true|false>,
    "nav_alignment": "<ok|problematic>",
    "cart_button_visible": <true|false>
  },
  "featured_products": {
    "score": <1-10>,
    "card_design_notes": "...",
    "grid_spacing_notes": "..."
  },
  "must_fix": ["top 3 things to fix first"]
}

Be specific. If the WebGL backdrop should cover the full hero but only appears in a corner, SAY SO. If typography is too loose or too tight, give exact px estimates."""

PROMPT_SPA_MOBILE_HOME = """Critique this MOBILE (375px wide) screenshot of a website homepage. The site is a Runway-style editorial design that supports BOTH light and dark themes — do not penalize light mode for "not being dark". Score each element 1-10, be honest, no rubber-stamping.

Return JSON:
{
  "overall_score": <1-10>,
  "header_mobile": {
    "score": <1-10>,
    "brand_wraps": <true|false>,
    "cart_button_clipped": <true|false>,
    "nav_visible": <true|false>,
    "touch_targets_adequate": <true|false>,
    "issues": [...]
  },
  "hero_mobile": {
    "score": <1-10>,
    "text_readable": <true|false>,
    "cta_touch_size": "<adequate|too small>",
    "webgl_backdrop_visible": <true|false>,
    "webgl_positioning_note": "<describe where backdrop shows relative to the hero text — does it extend behind the whole hero section, or only part?>"
  },
  "featured_mobile": {"score": <1-10>, "notes": "..."},
  "must_fix": [...]
}"""

PROMPT_SPA_CART_FLASH = """This is a screenshot of a shopping cart side drawer. A flash/highlight animation is supposed to be subtly highlighting an item row when quantity changes. Look carefully for:

1. Is there a visible rectangular or colored highlight on any line item?
2. If yes, do the EDGES of that highlight look CLIPPED or CUT OFF on the left or right side? Specifically: does the highlight look like it starts or stops abruptly at an edge rather than fading naturally or covering the full item width?
3. Is the highlight clipping AT the item border, or BEYOND it?

Return JSON:
{
  "flash_visible": <true|false>,
  "flash_clipping_on_sides": <true|false>,
  "where_clipping_occurs": "<describe exactly where the edges look cut: left edge of item, right edge of item, at the border-bottom, extending beyond the padding, etc.>",
  "is_covered_by_sidebar_or_element": "<describe any overlapping element that might be clipping the flash>",
  "recommended_fix": "<one sentence recommendation>",
  "severity": "<none|minor|moderate|bad>"
}"""

PROMPT_WP_CHECKOUT_DESKTOP = """This is a WooCommerce /checkout page screenshot on desktop. The target aesthetic is Runway-like: Inter typeface, clean forms, zero shadows, tight editorial feel. The site supports BOTH light and dark themes — evaluate this theme on its own merits, do not penalize for "not being dark". Critique mercilessly otherwise.

Return JSON:
{
  "overall_score": <1-10>,
  "is_it_recognizable_as_same_brand_as_SPA": <true|false>,
  "issues": ["issue 1", "issue 2", ...],
  "form_field_styling": {
    "score": <1-10>,
    "notes": "..."
  },
  "order_summary_table": {
    "score": <1-10>,
    "notes": "..."
  },
  "payment_section": {
    "score": <1-10>,
    "notes": "..."
  },
  "place_order_button": {
    "score": <1-10>,
    "notes": "..."
  },
  "theme_toggle_visible": <true|false>,
  "theme_toggle_location": "<description or 'not visible'>",
  "looks_chopped_or_unfinished": <true|false>,
  "chopped_description": "<describe anything that looks broken, incomplete, unstyled, or weird>",
  "must_fix": [...]
}"""

PROMPT_WP_MY_ACCOUNT_DESKTOP = """This is a WooCommerce /my-account page screenshot on desktop (user not logged in, showing login form). Target aesthetic: Runway-style, Inter, clean editorial. The site supports BOTH light and dark themes as first-class — do not penalize this theme just for "not being dark". Be brutal on execution, not on theme choice.

Return JSON:
{
  "overall_score": <1-10>,
  "login_form_score": <1-10>,
  "layout_issues": [...],
  "typography_issues": [...],
  "theme_toggle_visible": <true|false>,
  "looks_chopped_or_unfinished": <true|false>,
  "chopped_description": "...",
  "must_fix": [...]
}"""

PROMPT_WP_MOBILE = """This is a mobile (375px) screenshot of a WooCommerce native page. Target aesthetic: Runway-style, tight mobile layout, 44px touch targets, 16px input fonts. The site supports both light and dark themes — do NOT penalize for theme choice. Be honest about layout, typography, touch targets, and overall polish.

Return JSON:
{
  "overall_score": <1-10>,
  "layout_issues": [...],
  "touch_target_concerns": [...],
  "theme_toggle_visible": <true|false>,
  "looks_chopped_or_unfinished": <true|false>,
  "chopped_description": "...",
  "must_fix": [...]
}"""


TARGETS = [
    # (label, screenshot path, prompt)
    ("desktop_spa_home_dark", SHOTS / "laptop-dark-01-spa-home.png", PROMPT_SPA_DESKTOP_HOME),
    ("desktop_spa_home_light", SHOTS / "laptop-light-01-spa-home.png", PROMPT_SPA_DESKTOP_HOME),
    ("mobile_spa_home_dark", SHOTS / "mobile-dark-01-spa-home.png", PROMPT_SPA_MOBILE_HOME),
    ("mobile_spa_home_light", SHOTS / "mobile-light-01-spa-home.png", PROMPT_SPA_MOBILE_HOME),
    ("desktop_wp_checkout_dark", SHOTS / "laptop-dark-05-wp-checkout.png", PROMPT_WP_CHECKOUT_DESKTOP),
    ("desktop_wp_checkout_light", SHOTS / "laptop-light-05-wp-checkout.png", PROMPT_WP_CHECKOUT_DESKTOP),
    ("mobile_wp_checkout_dark", SHOTS / "mobile-dark-05-wp-checkout.png", PROMPT_WP_MOBILE),
    ("mobile_wp_checkout_light", SHOTS / "mobile-light-05-wp-checkout.png", PROMPT_WP_MOBILE),
    ("desktop_wp_myaccount_dark", SHOTS / "laptop-dark-06-wp-my-account.png", PROMPT_WP_MY_ACCOUNT_DESKTOP),
    ("desktop_wp_myaccount_light", SHOTS / "laptop-light-06-wp-my-account.png", PROMPT_WP_MY_ACCOUNT_DESKTOP),
    ("desktop_wp_login_dark", SHOTS / "laptop-dark-07-wp-login.png", PROMPT_WP_MY_ACCOUNT_DESKTOP),
    ("mobile_wp_myaccount_dark", SHOTS / "mobile-dark-06-wp-my-account.png", PROMPT_WP_MOBILE),
]


def main():
    api_key = load_api_key()
    results: dict[str, dict] = {}

    for label, path, prompt in TARGETS:
        if not path.exists():
            print(f"! missing {path.name} — skipping")
            continue
        print(f"analyzing {label} ({path.name})... ", end="", flush=True)
        try:
            result = analyze(path, prompt, api_key)
        except requests.HTTPError as e:
            print(f"HTTP ERROR: {e}")
            result = {"_error": str(e)}
        except Exception as e:
            print(f"ERROR: {e}")
            result = {"_error": str(e)}
        else:
            score = result.get("overall_score", "?")
            print(f"done (score: {score})")

        results[label] = result
        (OUT / f"{label}.json").write_text(json.dumps(result, indent=2))

    # Summary markdown
    lines = ["# Gemini 2.5 Pro Visual Critique\n"]
    lines.append(f"Analyzed {len(results)} screenshots with {MODEL}.\n")
    lines.append("---\n")

    for label, r in results.items():
        lines.append(f"## {label}\n")
        if "_error" in r:
            lines.append(f"**ERROR**: {r['_error']}\n")
            continue
        if "_raw" in r:
            lines.append(f"**parse error**, raw:\n\n```\n{r['_raw'][:500]}\n```\n")
            continue
        overall = r.get("overall_score")
        verdict = r.get("overall_verdict", "")
        if overall is not None:
            lines.append(f"**Overall**: {overall}/10 — {verdict}\n")
        # Must-fix
        mf = r.get("must_fix", [])
        if mf:
            lines.append("**Must fix:**")
            for item in mf:
                lines.append(f"- {item}")
            lines.append("")
        # Specific flags
        if r.get("looks_chopped_or_unfinished"):
            lines.append(f"**CHOPPED**: {r.get('chopped_description', '')}\n")
        if "theme_toggle_visible" in r:
            visible = r["theme_toggle_visible"]
            loc = r.get("theme_toggle_location", "")
            lines.append(f"**Theme toggle visible**: {visible} ({loc})\n")
        if "hero" in r:
            hero = r["hero"]
            lines.append(f"**Hero**: {hero.get('score', '?')}/10")
            if hero.get("webgl_cropped"):
                lines.append(f"- **WebGL CROPPED**: {hero.get('webgl_cropping_note', '')}")
            if not hero.get("webgl_backdrop_visible", True):
                lines.append("- **WebGL NOT VISIBLE**")
            lines.append(f"- Coverage: {hero.get('webgl_coverage', 'n/a')}")
            for issue in hero.get("issues", []):
                lines.append(f"- {issue}")
            lines.append("")

    (OUT / "_summary.md").write_text("\n".join(lines))
    print(f"\nsummary written to {OUT / '_summary.md'}")


if __name__ == "__main__":
    main()
