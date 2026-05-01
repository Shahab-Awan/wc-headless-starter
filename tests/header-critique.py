#!/usr/bin/env python3
"""
Gemini 2.5 Pro visual critique focused on header consistency and FK offer
page theming across all viewports and themes.
"""
import base64, json, os, sys
from pathlib import Path
import requests

ROOT = Path(__file__).resolve().parent.parent
SHOTS = ROOT / "tests" / "screenshots" / "header-final"
OUT = ROOT / "tests" / "critique" / "header"
OUT.mkdir(parents=True, exist_ok=True)

API_KEY = (Path.home() / ".config" / "gemini" / "api-key").read_text().strip()
MODEL = "gemini-2.5-pro"

PROMPT = """You are a senior front-end designer evaluating header/navigation consistency across a headless e-commerce site.

The site uses SvelteKit for the customer-facing SPA and WordPress for native pages (checkout, my-account, login, FK one-click upsell offer page). The design system targets Runway-style editorial aesthetics: Inter typeface, tight letter-spacing, glass-morphism sticky header, token-based colors (light + dark), zero border-radius on most elements.

You are looking at: {description}

Evaluate these aspects. Score each 1-10, be brutally honest:

1. **Header visual match**: Does this page's header look identical to the SPA header? Same brand typography, nav spacing, toggle button, cart button styling?
2. **Header positioning**: Is the header at the top, sticky, correct z-index? Any overflow or clipping on mobile?
3. **Typography consistency**: Is Inter used throughout? Are weights, sizes, letter-spacing consistent with the SPA?
4. **Color token adherence**: Are all colors using the design tokens? No hardcoded hex leaking through? In dark mode, does everything invert properly?
5. **Content layout**: Below the header, is the page content well-structured? Proper spacing, alignment, responsive behavior?
6. **Overall design coherence**: Does this page feel like it belongs to the same product as the SPA, or does it look like a different website?

Return JSON:
{{
  "page": "{page_name}",
  "viewport": "{viewport}",
  "theme": "{theme}",
  "header_match_score": <1-10>,
  "header_positioning_score": <1-10>,
  "typography_score": <1-10>,
  "color_token_score": <1-10>,
  "content_layout_score": <1-10>,
  "overall_coherence_score": <1-10>,
  "average_score": <float>,
  "issues": ["issue1", "issue2"],
  "what_works": ["thing1", "thing2"],
  "verdict": "<one sentence>"
}}
"""

def analyze(image_path, prompt):
    b64 = base64.b64encode(image_path.read_bytes()).decode("ascii")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent?key={API_KEY}"
    payload = {
        "contents": [{"parts": [
            {"text": prompt},
            {"inline_data": {"mime_type": "image/png", "data": b64}},
        ]}],
        "generationConfig": {
            "temperature": 0.1,
            "maxOutputTokens": 4000,
            "responseMimeType": "application/json",
        },
    }
    r = requests.post(url, json=payload, timeout=180)
    r.raise_for_status()
    text = r.json()["candidates"][0]["content"]["parts"][0]["text"].strip()
    if text.startswith("```"):
        lines = text.split("\n")
        text = "\n".join(lines[1:-1] if lines[-1].startswith("```") else lines[1:]).strip()
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        return {"_raw": text, "_error": True}

TARGETS = [
    ("desktop-light-spa-home", "SPA home page, desktop, light theme — this is the REFERENCE header that all WP pages should match"),
    ("desktop-light-wp-checkout", "WP native checkout page, desktop, light theme — should have identical header to SPA"),
    ("desktop-light-wp-my-account", "WP native my-account page, desktop, light theme"),
    ("desktop-light-wp-login", "WP login page, desktop, light theme"),
    ("desktop-light-upsell-offer", "WCHS one-click upsell offer page, desktop, light theme"),
    ("desktop-dark-spa-home", "SPA home page, desktop, dark theme — REFERENCE for dark mode"),
    ("desktop-dark-wp-checkout", "WP checkout, desktop, dark theme"),
    ("desktop-dark-wp-login", "WP login, desktop, dark theme"),
    ("desktop-dark-fk-offer", "FK upsell offer, desktop, dark theme"),
    ("mobile-light-spa-home", "SPA home, mobile 375px, light — REFERENCE for mobile header with hamburger"),
    ("mobile-light-wp-checkout", "WP checkout, mobile 375px, light"),
    ("mobile-light-wp-login", "WP login, mobile 375px, light"),
    ("mobile-light-fk-offer", "FK offer, mobile 375px, light"),
    ("mobile-dark-fk-offer", "FK offer, mobile 375px, dark"),
]

results = []
for label, desc in TARGETS:
    img = SHOTS / f"{label}.png"
    if not img.exists():
        print(f"  skip {label} (not found)")
        continue
    parts = label.split("-", 2)
    viewport = parts[0]
    theme = parts[1]
    page_name = parts[2] if len(parts) > 2 else label
    prompt = PROMPT.format(description=desc, page_name=page_name, viewport=viewport, theme=theme)
    print(f"→ {label}...", end=" ", flush=True)
    try:
        r = analyze(img, prompt)
        r["_label"] = label
        results.append(r)
        avg = r.get("average_score", 0)
        print(f"avg={avg}")
        (OUT / f"{label}.json").write_text(json.dumps(r, indent=2))
    except Exception as e:
        print(f"ERROR: {e}")
        results.append({"_label": label, "_error": str(e)})

# Summary
print("\n" + "=" * 60)
print("HEADER DESIGN AUDIT SUMMARY")
print("=" * 60)
total = 0
count = 0
for r in results:
    if "_error" in r:
        print(f"  {r['_label']}: ERROR")
        continue
    avg = r.get("average_score", 0)
    total += avg
    count += 1
    verdict = r.get("verdict", "")
    print(f"  {r['_label']}: {avg:.1f}/10 — {verdict}")

if count:
    print(f"\n  OVERALL AVERAGE: {total/count:.1f}/10 across {count} pages")

summary = {"results": results, "overall_average": round(total/count, 1) if count else 0}
(OUT / "_summary.json").write_text(json.dumps(summary, indent=2))
print(f"\n  Results saved to {OUT}")
