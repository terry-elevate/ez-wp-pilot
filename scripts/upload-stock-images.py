#!/usr/bin/env python3
"""Upload stock images to WordPress via OpenAI image generation.
Generates HVAC-related images and uploads them to the WP media library,
then updates the ez_img_pool option with the new attachment IDs.

Fix: Uses the shared ./scripts volume mount (accessible from both wp-pilot and wpcli containers)
instead of docker cp to wp-pilot (which wpcli can't see).
"""

import os, sys, json, base64, subprocess, time
from pathlib import Path

try:
    from openai import OpenAI
except ImportError:
    print("pip install openai")
    sys.exit(1)

env_file = Path(__file__).resolve().parent.parent.parent / ".env"
if env_file.exists():
    for line in env_file.read_text().splitlines():
        if line.startswith("OPENAI_API_KEY="):
            os.environ["OPENAI_API_KEY"] = line.split("=", 1)[1].strip()

client = OpenAI()

DOCKER_COMPOSE = str(Path(__file__).resolve().parent.parent / "docker-compose.yml")
SCRIPTS_DIR = Path(__file__).resolve().parent
TMP_DIR = SCRIPTS_DIR / "tmp"
TMP_DIR.mkdir(exist_ok=True)

IMAGE_PROMPTS = {
    "technician": [
        "Professional HVAC technician in blue uniform inspecting a residential furnace, warm basement lighting, photorealistic",
        "Friendly HVAC service technician explaining a thermostat to a homeowner in bright kitchen, photorealistic",
    ],
    "furnace": [
        "Modern high-efficiency gas furnace in a clean residential basement, warm lighting, professional photo",
    ],
    "ductwork": [
        "Clean silver HVAC ductwork running through a residential attic, professional photo",
    ],
    "thermostat": [
        "Modern smart thermostat on a white wall showing 72 degrees, minimalist home interior, product photography",
    ],
    "heatpump": [
        "Outdoor heat pump condenser unit beside a suburban home, green grass, blue sky, professional photo",
    ],
    "minisplit": [
        "Ductless mini-split indoor unit mounted on wall in a modern living room, professional photo",
    ],
    "condenser": [
        "Residential air conditioning condenser on concrete pad beside house, professional photo",
    ],
    "rowhouse": [
        "Row of Philadelphia-style brick rowhouses on tree-lined street, autumn colors, architectural photo",
    ],
    "suburban": [
        "Pennsylvania suburban home with garage, manicured lawn, autumn trees, real estate photography",
    ],
    "commercial": [
        "Commercial HVAC rooftop unit on a small office building, professional photo",
    ],
    "filter": [
        "Person holding dirty HVAC filter next to clean new one, basement setting, tutorial photo",
    ],
    "radiator": [
        "Cast iron steam radiator in a Victorian-era room with wood floors, warm afternoon light",
    ],
}


def generate_image(prompt: str) -> bytes:
    """Generate an image using OpenAI gpt-image-1 and return bytes."""
    response = client.images.generate(
        model="gpt-image-1",
        prompt=prompt,
        size="1536x1024",
        quality="low",
        n=1,
    )
    return base64.b64decode(response.data[0].b64_json)


def wp_cli(cmd: str) -> str:
    """Run a wp-cli command via docker compose."""
    full_cmd = f'docker compose -f {DOCKER_COMPOSE} run --rm -T wpcli {cmd}'
    result = subprocess.run(full_cmd, shell=True, capture_output=True, text=True, timeout=60)
    output = result.stdout.strip()
    lines = [l for l in output.splitlines() if not any(x in l for x in ['Container', 'Creating', 'Running', 'Waiting', 'Healthy', 'Created'])]
    return "\n".join(lines)


def upload_image(img_bytes: bytes, filename: str, title: str) -> int:
    """Upload image bytes to WordPress via the shared scripts volume mount."""
    # Write to ./scripts/tmp/ which maps to /var/www/html/scripts/tmp/ in wpcli
    local_path = TMP_DIR / filename
    local_path.write_bytes(img_bytes)

    # Import from the container-accessible path
    container_path = f"/var/www/html/scripts/tmp/{filename}"
    output = wp_cli(f'media import {container_path} --title="{title}" --porcelain')
    try:
        att_id = int(output.strip())
        local_path.unlink(missing_ok=True)
        return att_id
    except ValueError:
        print(f"  Failed to parse attachment ID: {output}")
        local_path.unlink(missing_ok=True)
        return 0


def main():
    print("=== Stock Image Generator & Uploader ===")
    print(f"Topics: {len(IMAGE_PROMPTS)}")
    total_prompts = sum(len(v) for v in IMAGE_PROMPTS.values())
    print(f"Total images to generate: {total_prompts}")
    print()

    pool = {}
    generated = 0
    failed = 0

    for topic, prompts in IMAGE_PROMPTS.items():
        pool[topic] = []
        for i, prompt in enumerate(prompts):
            print(f"  [{generated+1}/{total_prompts}] {topic} #{i+1}...", end=" ", flush=True)
            try:
                img_bytes = generate_image(prompt)
                filename = f"ez-{topic}-{i+1}.png"
                title = f"EZ Stock: {topic} {i+1}"
                att_id = upload_image(img_bytes, filename, title)
                if att_id:
                    pool[topic].append(att_id)
                    wp_cli(f'post meta update {att_id} _wp_attachment_image_alt "{topic} - professional HVAC service photo"')
                    wp_cli(f'post meta update {att_id} _ez_attribution "AI-generated (gpt-image-1)"')
                    print(f"✓ ID={att_id}")
                    generated += 1
                else:
                    print("✗ upload failed")
                    failed += 1
            except Exception as e:
                print(f"✗ {str(e)[:80]}")
                failed += 1
            time.sleep(2)  # Rate limit

    # Merge with existing pool
    print(f"\n--- Updating ez_img_pool option ---")
    existing_pool_json = wp_cli('eval "echo json_encode(get_option(\'ez_img_pool\', []));"')
    try:
        existing_pool = json.loads(existing_pool_json)
    except json.JSONDecodeError:
        existing_pool = {}

    for topic, ids in pool.items():
        if ids:
            existing = existing_pool.get(topic, [])
            existing_pool[topic] = existing + ids

    pool_json = json.dumps(existing_pool)
    wp_cli(f"eval \"update_option('ez_img_pool', json_decode('{pool_json}', true));\"")

    print(f"\nDone: {generated} generated, {failed} failed")
    print(f"Pool now has {sum(len(v) for v in existing_pool.values())} total images across {len(existing_pool)} topics")


if __name__ == "__main__":
    main()
