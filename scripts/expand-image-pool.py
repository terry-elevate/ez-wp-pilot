#!/usr/bin/env python3
"""Expand ez_img_pool from 40 to 80+ images across 20 topics.
Generates with gemini-2.5-flash-image (Nano Banana, ~$0.039/image, ~6s),
uploads via the shared scripts volume, MERGES into the existing pool option.
"""

import json, base64, subprocess, time, urllib.request
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent.parent
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

DOCKER_COMPOSE = str(Path(__file__).resolve().parent.parent / "docker-compose.yml")
TMP_DIR = Path(__file__).resolve().parent / "tmp"
TMP_DIR.mkdir(exist_ok=True)

MODEL = "gemini-2.5-flash-image"
ATTR = f"AI-generated ({MODEL})"

STYLE = ", photorealistic, professional editorial photography, natural light"

# New depth on existing topics + 8 new topics → ~44 new images
PROMPTS = {
    # existing topics, new angles
    "technician": [
        "HVAC technician checking refrigerant gauges on outdoor unit, kneeling on grass" + STYLE,
        "Two HVAC technicians carrying an air handler up porch steps of an old brick house" + STYLE,
    ],
    "furnace": [
        "Close-up of burner flames inside a gas furnace, service panel open" + STYLE,
        "Technician flashlight inspection of a furnace heat exchanger in dim basement" + STYLE,
    ],
    "ductwork": [
        "Flexible and rigid ductwork junction in an unfinished basement ceiling" + STYLE,
        "New sealed ductwork with mastic at the seams in a crawlspace" + STYLE,
    ],
    "thermostat": [
        "Homeowner adjusting a round smart thermostat in a hallway, winter sweater" + STYLE,
        "Old mercury thermostat next to a new smart thermostat on a workbench" + STYLE,
    ],
    "heatpump": [
        "Cold-climate heat pump running in light snow beside a Pennsylvania home" + STYLE,
        "Technician wiring a heat pump disconnect box on brick exterior wall" + STYLE,
    ],
    "minisplit": [
        "Mini-split condenser bracket-mounted on the wall of a stone farmhouse" + STYLE,
        "Living room with wall-mounted mini-split above a bookshelf, cozy interior" + STYLE,
    ],
    "condenser": [
        "AC condenser being leveled on a new composite pad, technician with level" + STYLE,
    ],
    "rowhouse": [
        "Snow-dusted brick rowhouses at dusk with warm windows, Pennsylvania winter" + STYLE,
        "Narrow alley behind brick rowhouses showing utility meters and small yards" + STYLE,
    ],
    "suburban": [
        "Ranch house with fresh snow on the roof, cleared driveway, winter morning sun" + STYLE,
        "1970s split-level home with mature oak trees, summer afternoon" + STYLE,
    ],
    "commercial": [
        "Small-town Pennsylvania main street storefronts with apartments above" + STYLE,
    ],
    "filter": [
        "Rack of furnace filters in a hardware store aisle, hand comparing sizes" + STYLE,
    ],
    "radiator": [
        "Steam radiator under a bay window with peeling Victorian trim, morning light" + STYLE,
    ],
    # new topics
    "boiler": [
        "Modern condensing boiler with copper piping in a tidy utility closet" + STYLE,
        "Old cast iron boiler in a stone-walled Pennsylvania basement" + STYLE,
    ],
    "insulation": [
        "Blown-in attic insulation with ruler showing depth, roof rafters visible" + STYLE,
        "Technician air-sealing rim joists with foam in a basement" + STYLE,
    ],
    "waterheater": [
        "Tankless water heater mounted on basement wall with new PEX lines" + STYLE,
    ],
    "venting": [
        "New PVC intake and exhaust vents through the side wall of a house" + STYLE,
        "Chimney liner being lowered into a brick chimney, roof view" + STYLE,
    ],
    "zoning": [
        "Motorized zone dampers installed in trunk ductwork with labeled wiring" + STYLE,
        "Multi-zone thermostat control panel in a hallway closet" + STYLE,
    ],
    "victorian": [
        "Queen Anne Victorian house with turret and wraparound porch, autumn" + STYLE,
        "Ornate Victorian interior with high ceilings and a marble fireplace" + STYLE,
    ],
    "farmhouse": [
        "Stone farmhouse with red barn in Lancaster County countryside, golden hour" + STYLE,
        "Limestone farmhouse kitchen with exposed beams and deep windowsills" + STYLE,
    ],
    "winter": [
        "Snow plow passing brick storefronts in a small Pennsylvania town at dawn" + STYLE,
        "Icicles on the eaves of an old house, deep snow, bright winter sky" + STYLE,
    ],
}


def generate(prompt: str) -> bytes:
    req = urllib.request.Request(
        f"https://generativelanguage.googleapis.com/v1beta/models/{MODEL}:generateContent",
        data=json.dumps({"contents": [{"parts": [{"text": prompt}]}],
                         "generationConfig": {"responseModalities": ["IMAGE"]}}).encode(),
        headers={"Content-Type": "application/json", "x-goog-api-key": KEY})
    with urllib.request.urlopen(req, timeout=120) as r:
        d = json.load(r)
    b64 = next(p["inlineData"]["data"]
               for c in d.get("candidates", [])
               for p in c.get("content", {}).get("parts", [])
               if "inlineData" in p)
    return base64.b64decode(b64)


def wp_cli(cmd: str) -> str:
    full = f'docker compose -f {DOCKER_COMPOSE} run --rm -T wpcli {cmd}'
    res = subprocess.run(full, shell=True, capture_output=True, text=True, timeout=90)
    lines = [l for l in res.stdout.strip().splitlines()
             if not any(x in l for x in ['Container', 'Creating', 'Running', 'Waiting', 'Healthy', 'Created'])]
    return "\n".join(lines)


total = sum(len(v) for v in PROMPTS.values())
print(f"Generating {total} images with {MODEL} (~${total * 0.039:.2f})")

new_pool = {}
n = 0
for topic, prompts in PROMPTS.items():
    new_pool[topic] = []
    for i, prompt in enumerate(prompts):
        n += 1
        print(f"[{n}/{total}] {topic} #{i+1}...", end=" ", flush=True)
        try:
            img = generate(prompt)
            fname = f"ez2-{topic}-{i+1}.png"
            (TMP_DIR / fname).write_bytes(img)
            out = wp_cli(f'media import /var/www/html/scripts/tmp/{fname} --title="EZ Stock: {topic} {i+1} (v2)" --porcelain')
            att_id = int(out.strip())
            wp_cli(f'post meta update {att_id} _wp_attachment_image_alt "{topic} - professional HVAC service photo"')
            wp_cli(f'post meta update {att_id} _ez_attribution "{ATTR}"')
            (TMP_DIR / fname).unlink(missing_ok=True)
            new_pool[topic].append(att_id)
            print(f"ok id={att_id}")
        except Exception as e:
            print(f"FAILED: {str(e)[:120]}")

# Merge into existing pool
merge_json = json.dumps(new_pool)
(TMP_DIR / "pool-merge.json").write_text(merge_json)
merge_php = (
    '$new = json_decode(file_get_contents("/var/www/html/scripts/tmp/pool-merge.json"), true);'
    '$pool = get_option("ez_img_pool", []);'
    'foreach ($new as $topic => $ids) {'
    '  $pool[$topic] = array_values(array_unique(array_merge($pool[$topic] ?? [], $ids)));'
    '}'
    'update_option("ez_img_pool", $pool);'
    '$n = 0; foreach ($pool as $ids) { $n += count($ids); }'
    'echo "pool now: " . count($pool) . " topics, " . $n . " images\\n";'
)
print(wp_cli(f"eval '{merge_php}'"))
