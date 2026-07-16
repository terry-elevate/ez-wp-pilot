#!/usr/bin/env python3
"""Same prompt as image-model-matrix.py through Gemini/Imagen image models.
Reads GEMINI_API_KEY from ~/Repo/.env or sibling project .env files.
Output: scripts/tmp/matrix/results-gemini.json + PNGs."""

import json, base64, time, urllib.request
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent  # ~/Repo
KEY = ""
for env in [REPO / ".env", REPO / "buller" / ".env", REPO / "ai-practice-shopping" / ".env"]:
    if env.exists():
        for line in env.read_text().splitlines():
            if line.startswith("GEMINI_API_KEY=") and line.split("=", 1)[1].strip():
                KEY = line.split("=", 1)[1].strip().strip('"')
                break
    if KEY:
        break
if not KEY:
    raise SystemExit("no GEMINI_API_KEY found")

OUT_DIR = Path(__file__).resolve().parent / "tmp" / "matrix"
OUT_DIR.mkdir(parents=True, exist_ok=True)

PROMPT = ("Professional HVAC technician in blue uniform inspecting a modern "
          "high-efficiency gas furnace in a clean residential basement, warm "
          "lighting, photorealistic, editorial photography")

BASE = "https://generativelanguage.googleapis.com/v1beta/models"

GEMINI_MODELS = ["gemini-2.5-flash-image", "gemini-3.1-flash-image", "gemini-3-pro-image"]
IMAGEN_MODELS = ["imagen-4.0-fast-generate-001", "imagen-4.0-generate-001", "imagen-4.0-ultra-generate-001"]


def post(url, payload, timeout=300):
    req = urllib.request.Request(
        url, data=json.dumps(payload).encode(),
        headers={"Content-Type": "application/json", "x-goog-api-key": KEY})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return json.load(r)


results = []

for model in GEMINI_MODELS:
    print(f"→ {model} ...", flush=True)
    t0 = time.time()
    try:
        d = post(f"{BASE}/{model}:generateContent",
                 {"contents": [{"parts": [{"text": PROMPT}]}],
                  "generationConfig": {"responseModalities": ["IMAGE"]}})
        elapsed = round(time.time() - t0, 1)
        img_b64 = next(p["inlineData"]["data"]
                       for c in d.get("candidates", [])
                       for p in c.get("content", {}).get("parts", [])
                       if "inlineData" in p)
        fname = f"{model}.png"
        (OUT_DIR / fname).write_bytes(base64.b64decode(img_b64))
        usage = d.get("usageMetadata", {})
        results.append({"model": model, "quality": "default", "file": fname,
                        "seconds": elapsed, "usage": usage, "ok": True})
        print(f"  ok {elapsed}s tokens={usage.get('totalTokenCount','?')}", flush=True)
    except Exception as e:
        results.append({"model": model, "quality": "default",
                        "seconds": round(time.time() - t0, 1),
                        "error": str(e)[:300], "ok": False})
        print(f"  FAILED: {str(e)[:200]}", flush=True)

for model in IMAGEN_MODELS:
    print(f"→ {model} ...", flush=True)
    t0 = time.time()
    try:
        d = post(f"{BASE}/{model}:predict",
                 {"instances": [{"prompt": PROMPT}],
                  "parameters": {"sampleCount": 1, "aspectRatio": "1:1"}})
        elapsed = round(time.time() - t0, 1)
        img_b64 = d["predictions"][0]["bytesBase64Encoded"]
        fname = f"{model}.png"
        (OUT_DIR / fname).write_bytes(base64.b64decode(img_b64))
        results.append({"model": model, "quality": "default", "file": fname,
                        "seconds": elapsed, "usage": {}, "ok": True})
        print(f"  ok {elapsed}s", flush=True)
    except Exception as e:
        results.append({"model": model, "quality": "default",
                        "seconds": round(time.time() - t0, 1),
                        "error": str(e)[:300], "ok": False})
        print(f"  FAILED: {str(e)[:200]}", flush=True)

(OUT_DIR / "results-gemini.json").write_text(json.dumps(results, indent=2))
print(f"\nWrote {OUT_DIR / 'results-gemini.json'}")
