#!/usr/bin/env python3
"""Run the same prompt through every available OpenAI image model,
capture latency + token usage, save images to scripts/tmp/matrix/.
Output: scripts/tmp/matrix/results.json
"""

import os, sys, json, base64, time
from pathlib import Path

from openai import OpenAI

env_file = Path(__file__).resolve().parent.parent.parent / ".env"
if env_file.exists():
    for line in env_file.read_text().splitlines():
        if line.startswith("OPENAI_API_KEY="):
            os.environ["OPENAI_API_KEY"] = line.split("=", 1)[1].strip()

client = OpenAI()

OUT_DIR = Path(__file__).resolve().parent / "tmp" / "matrix"
OUT_DIR.mkdir(parents=True, exist_ok=True)

PROMPT = ("Professional HVAC technician in blue uniform inspecting a modern "
          "high-efficiency gas furnace in a clean residential basement, warm "
          "lighting, photorealistic, editorial photography")

RUNS = [
    {"model": "gpt-image-1-mini", "quality": "medium"},
    {"model": "gpt-image-1",      "quality": "low"},
    {"model": "gpt-image-1",      "quality": "medium"},
    {"model": "gpt-image-1",      "quality": "high"},
    {"model": "gpt-image-1.5",    "quality": "medium"},
    {"model": "gpt-image-2",      "quality": "medium"},
    {"model": "gpt-image-2",      "quality": "high"},
]

results = []
for run in RUNS:
    tag = f"{run['model']}-{run['quality']}"
    print(f"→ {tag} ...", flush=True)
    t0 = time.time()
    try:
        resp = client.images.generate(
            model=run["model"],
            prompt=PROMPT,
            size="1024x1024",
            quality=run["quality"],
            n=1,
        )
        elapsed = round(time.time() - t0, 1)
        img_b64 = resp.data[0].b64_json
        fname = f"{tag}.png"
        (OUT_DIR / fname).write_bytes(base64.b64decode(img_b64))
        usage = getattr(resp, "usage", None)
        usage_d = usage.model_dump() if usage else {}
        results.append({
            "model": run["model"], "quality": run["quality"],
            "file": fname, "seconds": elapsed, "usage": usage_d, "ok": True,
        })
        print(f"  ok {elapsed}s usage={usage_d}", flush=True)
    except Exception as e:
        elapsed = round(time.time() - t0, 1)
        results.append({
            "model": run["model"], "quality": run["quality"],
            "seconds": elapsed, "error": str(e)[:300], "ok": False,
        })
        print(f"  FAILED {elapsed}s: {str(e)[:200]}", flush=True)

(OUT_DIR / "results.json").write_text(json.dumps(results, indent=2))
print(f"\nWrote {OUT_DIR / 'results.json'}")
