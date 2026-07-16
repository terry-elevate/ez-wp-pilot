#!/usr/bin/env python3
"""Top up thin topics to push ez_img_pool past 80 images."""
import json, base64, subprocess, urllib.request
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
KEY = ""
for env in [REPO / ".env", REPO / "buller" / ".env"]:
    if env.exists():
        for line in env.read_text().splitlines():
            if line.startswith("GEMINI_API_KEY=") and line.split("=", 1)[1].strip():
                KEY = line.split("=", 1)[1].strip().strip('"')
if not KEY:
    raise SystemExit("no key")

DOCKER_COMPOSE = str(Path(__file__).resolve().parent.parent / "docker-compose.yml")
TMP_DIR = Path(__file__).resolve().parent / "tmp"
MODEL = "gemini-2.5-flash-image"
STYLE = ", photorealistic, professional editorial photography, natural light"

PROMPTS = {
    "waterheater": ["Technician descaling a tankless water heater with a service pump" + STYLE],
    "commercial": ["Rooftop HVAC units on a downtown Pennsylvania office building, drone view" + STYLE],
    "condenser": ["Two AC condensers side by side behind a duplex, one old one new" + STYLE],
    "filter": ["Homeowner sliding a clean pleated filter into a furnace slot" + STYLE],
    "radiator": ["Freshly painted silver radiator in a renovated rowhouse bedroom" + STYLE],
    "thermostat": ["Wall thermostat showing 68 degrees with snow visible through window behind" + STYLE],
    "insulation": ["Infrared thermal camera image view of heat loss around old windows" + STYLE],
    "winter": ["Heat pump running in falling snow, steam rising, evening blue light" + STYLE],
}


def wp_cli(cmd):
    full = f'docker compose -f {DOCKER_COMPOSE} run --rm -T wpcli {cmd}'
    res = subprocess.run(full, shell=True, capture_output=True, text=True, timeout=90)
    return "\n".join(l for l in res.stdout.strip().splitlines()
                     if not any(x in l for x in ['Container', 'Creating', 'Running', 'Waiting', 'Healthy', 'Created']))


new_pool = {}
for i, (topic, prompts) in enumerate(PROMPTS.items()):
    prompt = prompts[0]
    print(f"[{i+1}/8] {topic}...", end=" ", flush=True)
    try:
        req = urllib.request.Request(
            f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent",
            data=json.dumps({"contents": [{"parts": [{"text": prompt}]}],
                             "generationConfig": {"responseModalities": ["IMAGE"]}}).encode(),
            headers={"Content-Type": "application/json", "x-goog-api-key": KEY})
        with urllib.request.urlopen(req, timeout=120) as r:
            d = json.load(r)
        b64 = next(p["inlineData"]["data"] for c in d["candidates"]
                   for p in c["content"]["parts"] if "inlineData" in p)
        fname = f"ez3-{topic}.png"
        (TMP_DIR / fname).write_bytes(base64.b64decode(b64))
        att_id = int(wp_cli(f'media import /var/www/html/scripts/tmp/{fname} --title="EZ Stock: {topic} (v3)" --porcelain').strip())
        wp_cli(f'post meta update {att_id} _wp_attachment_image_alt "{topic} - professional HVAC service photo"')
        wp_cli(f'post meta update {att_id} _ez_attribution "AI-generated ({MODEL})"')
        (TMP_DIR / fname).unlink(missing_ok=True)
        new_pool.setdefault(topic, []).append(att_id)
        print(f"ok id={att_id}")
    except Exception as e:
        print(f"FAILED: {str(e)[:100]}")

(TMP_DIR / "pool-merge.json").write_text(json.dumps(new_pool))
merge_php = (
    '$new = json_decode(file_get_contents("/var/www/html/scripts/tmp/pool-merge.json"), true);'
    '$pool = get_option("ez_img_pool", []);'
    'foreach ($new as $t => $ids) { $pool[$t] = array_values(array_unique(array_merge($pool[$t] ?? [], $ids))); }'
    'update_option("ez_img_pool", $pool);'
    '$n = 0; foreach ($pool as $ids) { $n += count($ids); }'
    'echo "pool now: " . count($pool) . " topics, " . $n . " images\\n";'
)
print(wp_cli(f"eval '{merge_php}'"))
