#!/usr/bin/env python3
"""Build docs/image-matrix.html from scripts/tmp/matrix/results.json.
Copies images into docs/matrix/ so the page is self-contained."""

import json, shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
MATRIX = ROOT / "scripts" / "tmp" / "matrix"
DOCS_IMG = ROOT / "docs" / "matrix"
DOCS_IMG.mkdir(parents=True, exist_ok=True)

results = json.loads((MATRIX / "results.json").read_text())

# Published per-image prices, 1024x1024 (July 2026, developers.openai.com/api/docs/pricing
# + ai.google.dev/gemini-api/docs/pricing). Keyed by (model, quality).
PRICES = {
    ("gpt-image-1-mini", "medium"): 0.011,
    ("gpt-image-1", "low"):    0.011,
    ("gpt-image-1", "medium"): 0.042,
    ("gpt-image-1", "high"):   0.167,
    ("gpt-image-1.5", "medium"): 0.04,
    ("gpt-image-2", "medium"): 0.041,
    ("gpt-image-2", "high"):   0.165,
}

# Models we could not run (no API key) — published prices only.
NOT_RUN = [
    ("gemini-2.5-flash-image (Nano Banana)", 0.039, "No GEMINI_API_KEY in .env"),
    ("gemini-3.1-flash-image (Nano Banana 2)", 0.045, "No GEMINI_API_KEY in .env"),
    ("gemini-3-pro-image (Nano Banana Pro)", None, "No GEMINI_API_KEY in .env"),
    ("imagen-4", 0.04, "No GEMINI_API_KEY in .env"),
]

cards = []
rows = []
for r in results:
    tag = f"{r['model']} · {r['quality']}"
    if not r.get("ok"):
        rows.append(f"<tr><td>{tag}</td><td colspan=3 class='err'>failed: {r.get('error','?')}</td></tr>")
        continue
    shutil.copy2(MATRIX / r["file"], DOCS_IMG / r["file"])
    price = PRICES.get((r["model"], r["quality"]))
    price_s = f"${price:.3f}" if price else "—"
    out_tok = r.get("usage", {}).get("output_tokens", "—")
    cards.append(f"""
    <figure class="cell">
      <img src="matrix/{r['file']}" alt="{tag}" loading="lazy">
      <figcaption>
        <strong>{r['model']}</strong> <span class="q">{r['quality']}</span>
        <span class="meta">{price_s} · {r['seconds']}s · {out_tok} img tokens</span>
      </figcaption>
    </figure>""")
    rows.append(f"<tr><td>{r['model']}</td><td>{r['quality']}</td><td>{price_s}</td><td>{r['seconds']}s</td><td>{out_tok}</td></tr>")

nr_rows = "".join(
    f"<tr class='nr'><td>{name}</td><td>—</td><td>{'$%.3f' % p if p else 'n/a'}</td><td colspan=2>{why}</td></tr>"
    for name, p, why in NOT_RUN
)

html = f"""<title>Image Model Matrix — same prompt, every model</title>
<style>
:root {{
  --ground:#FAFAF7; --surface:#FFFFFF; --border:#E2E0DA;
  --ink:#1C2331; --ink2:#4A5568; --ink3:#8B919E; --accent:#4A7FBF;
}}
@media (prefers-color-scheme: dark) {{
  :root {{ --ground:#131517; --surface:#1A1D21; --border:#2E3238; --ink:#E4E2DD; --ink2:#A8ADB8; --ink3:#6B7280; --accent:#6BA3D6; }}
}}
:root[data-theme="dark"] {{ --ground:#131517; --surface:#1A1D21; --border:#2E3238; --ink:#E4E2DD; --ink2:#A8ADB8; --ink3:#6B7280; --accent:#6BA3D6; }}
:root[data-theme="light"] {{ --ground:#FAFAF7; --surface:#FFFFFF; --border:#E2E0DA; --ink:#1C2331; --ink2:#4A5568; --ink3:#8B919E; --accent:#4A7FBF; }}
body {{ background:var(--ground); color:var(--ink2); font:15px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif; margin:0; padding:48px 32px; }}
main {{ max-width:1080px; margin:0 auto; }}
h1 {{ font-family:Baskerville,'Libre Baskerville',Georgia,serif; font-weight:400; font-size:34px; color:var(--ink); margin:0 0 8px; }}
.sub {{ color:var(--ink3); margin:0 0 40px; max-width:65ch; }}
.grid {{ display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:20px; margin-bottom:56px; }}
.cell {{ margin:0; background:var(--surface); border:1px solid var(--border); border-radius:8px; overflow:hidden; }}
.cell img {{ width:100%; display:block; aspect-ratio:1; object-fit:cover; }}
.cell figcaption {{ padding:12px 14px; font-size:13px; color:var(--ink); display:flex; flex-wrap:wrap; gap:6px; align-items:baseline; }}
.cell .q {{ color:var(--accent); font-weight:600; }}
.cell .meta {{ color:var(--ink3); font-size:12px; width:100%; font-variant-numeric:tabular-nums; }}
h2 {{ font-size:15px; color:var(--ink); margin:0 0 12px; }}
.twrap {{ overflow-x:auto; }}
table {{ border-collapse:collapse; width:100%; background:var(--surface); border:1px solid var(--border); border-radius:8px; font-size:13.5px; }}
th,td {{ text-align:left; padding:10px 14px; border-bottom:1px solid var(--border); }}
th {{ color:var(--ink3); font-weight:500; font-size:12px; text-transform:uppercase; letter-spacing:.06em; }}
td {{ color:var(--ink); font-variant-numeric:tabular-nums; }}
tr:last-child td {{ border-bottom:none; }}
tr.nr td {{ color:var(--ink3); }}
.err {{ color:#B7472A; }}
.note {{ font-size:12.5px; color:var(--ink3); margin-top:14px; max-width:72ch; }}
</style>
<main>
<h1>Image model matrix</h1>
<p class="sub">One prompt — the HVAC technician + furnace shot — through every image model this account can reach, at 1024&times;1024. Judge quality by eye; cost and latency are measured below.</p>

<div class="grid">{"".join(cards)}
</div>

<h2>Cost &amp; speed</h2>
<div class="twrap">
<table>
<tr><th>Model</th><th>Quality</th><th>$ / image</th><th>Latency</th><th>Output tokens</th></tr>
{"".join(rows)}
{nr_rows}
</table>
</div>
<p class="note">Prices are the published 1024&times;1024 rates (July 2026). Gemini rows are listed for comparison but not generated — add GEMINI_API_KEY to .env and extend scripts/image-model-matrix.py to fill them in. The 51-page pilot used ~20 AI images: at gpt-image-1 medium that's ~$0.84 total; gpt-image-2 medium is the same money for the newer model.</p>
</main>
"""

out = ROOT / "docs" / "image-matrix.html"
out.write_text(html)
print(f"Wrote {out} with {len(cards)} images")
