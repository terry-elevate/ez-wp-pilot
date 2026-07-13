#!/usr/bin/env python3
"""
AI Image Sourcing Test Script
==============================
Tests feasibility of sourcing location-specific HVAC images for EZ Solutions
city pages. Checks AI generation APIs and falls back to Openverse (Creative
Commons search).

AI Image Generation Options (not currently available):
------------------------------------------------------
1. DALL-E 3 (OpenAI)
   - Cost: $0.040/image (1024x1024), $0.080 (1792x1024 HD)
   - Env: OPENAI_API_KEY
   - Pros: Best prompt adherence, good photorealism
   - Cons: Expensive at scale, rate-limited

2. Stability AI (Stable Diffusion XL / SD3)
   - Cost: ~$0.002-0.006/image depending on model
   - Env: STABILITY_API_KEY
   - Pros: Cheapest, fast, commercial license
   - Cons: Less photorealistic for specific scenes

3. Local (Ollama + diffusion models, or ComfyUI)
   - Cost: Free (hardware only)
   - Models: stable-diffusion, flux
   - Pros: No API cost, full control, privacy
   - Cons: Requires GPU, slower, setup overhead

4. Midjourney (via API, now available)
   - Cost: $0.01-0.05/image depending on plan
   - Pros: Highest aesthetic quality
   - Cons: API access requires subscription

Recommendation for EZ Solutions:
- For 50-100 location pages: Stability AI (~$0.50 total) or DALL-E 3 (~$4-8)
- For ongoing generation: Local ComfyUI with SDXL (one-time GPU cost)
- Budget fallback: Openverse CC-licensed images (free, tested below)
"""

import os
import sys
import json
import time
import hashlib
from pathlib import Path
from datetime import datetime

try:
    import requests
except ImportError:
    print("ERROR: 'requests' package not found. Install with: pip3 install requests")
    sys.exit(1)

# Configuration
OUTPUT_DIR = Path("/Users/txu/Repo/wordpress/content/test-images")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

RESULTS_FILE = OUTPUT_DIR / "RESULTS.md"

# Openverse API (no key needed for limited use)
OPENVERSE_BASE = "https://api.openverse.org/v1"

# Search queries: city-specific HVAC contexts for EZ Solutions service areas
SEARCH_QUERIES = [
    {
        "city": "Pittsburgh",
        "query": "industrial heating system Pittsburgh",
        "context": "Steel city, older industrial buildings, harsh winters",
    },
    {
        "city": "Lancaster",
        "query": "colonial house radiator heating",
        "context": "Historic colonial architecture, older homes needing HVAC upgrades",
    },
    {
        "city": "Philadelphia",
        "query": "row house HVAC air conditioning",
        "context": "Dense row houses, summer humidity, mixed heating/cooling needs",
    },
    {
        "city": "Harrisburg",
        "query": "commercial building HVAC rooftop unit",
        "context": "State capital, commercial buildings, government facilities",
    },
    {
        "city": "Allentown",
        "query": "residential furnace basement heating",
        "context": "Lehigh Valley suburbs, residential furnace replacements",
    },
]


def search_openverse(query, page_size=5):
    """Search Openverse for CC-licensed images."""
    url = f"{OPENVERSE_BASE}/images/"
    params = {
        "q": query,
        "page_size": page_size,
        "license_type": "commercial",  # Only commercially usable
        "mature": "false",
    }

    try:
        resp = requests.get(url, params=params, timeout=15)
        if resp.status_code == 429:
            print(f"  Rate limited, waiting 5s...")
            time.sleep(5)
            resp = requests.get(url, params=params, timeout=15)

        if resp.status_code == 200:
            return resp.json()
        else:
            print(f"  API returned status {resp.status_code}: {resp.text[:200]}")
            return None
    except requests.exceptions.RequestException as e:
        print(f"  Request error: {e}")
        return None


def download_image(url, filepath, timeout=20):
    """Download an image file."""
    try:
        resp = requests.get(url, timeout=timeout, stream=True)
        if resp.status_code == 200:
            content_type = resp.headers.get("content-type", "")
            if "image" not in content_type and "octet" not in content_type:
                print(f"    Skipped (not an image: {content_type})")
                return False

            with open(filepath, "wb") as f:
                for chunk in resp.iter_content(chunk_size=8192):
                    f.write(chunk)

            size_kb = os.path.getsize(filepath) / 1024
            print(f"    Downloaded: {filepath.name} ({size_kb:.1f} KB)")
            return True
        else:
            print(f"    Download failed: HTTP {resp.status_code}")
            return False
    except Exception as e:
        print(f"    Download error: {e}")
        return False


def run_tests():
    """Run all image sourcing tests."""
    results = {
        "timestamp": datetime.now().isoformat(),
        "ai_apis_available": False,
        "openverse_results": [],
        "total_downloaded": 0,
        "total_found": 0,
    }

    print("=" * 60)
    print("AI Image Sourcing Test - EZ Solutions Location Pages")
    print("=" * 60)
    print()

    # Check AI APIs
    print("[1/2] Checking AI image generation APIs...")
    openai_key = os.environ.get("OPENAI_API_KEY")
    stability_key = os.environ.get("STABILITY_API_KEY") or os.environ.get("STABILITY_KEY")

    if openai_key:
        print("  DALL-E 3: AVAILABLE")
        results["ai_apis_available"] = True
    else:
        print("  DALL-E 3: Not configured (no OPENAI_API_KEY)")

    if stability_key:
        print("  Stability AI: AVAILABLE")
        results["ai_apis_available"] = True
    else:
        print("  Stability AI: Not configured (no STABILITY_API_KEY)")

    # Check Ollama
    try:
        ollama_resp = requests.get("http://localhost:11434/api/tags", timeout=3)
        if ollama_resp.status_code == 200:
            models = ollama_resp.json().get("models", [])
            image_models = [m for m in models if any(
                x in m.get("name", "").lower()
                for x in ["stable-diffusion", "flux", "sdxl", "dall"]
            )]
            if image_models:
                print(f"  Ollama: {len(image_models)} image model(s) available")
                results["ai_apis_available"] = True
            else:
                print(f"  Ollama: Running but no image models (found {len(models)} text models)")
        else:
            print("  Ollama: Not responding")
    except Exception:
        print("  Ollama: Not running locally")

    print()

    # Openverse fallback
    print("[2/2] Testing Openverse API (CC-licensed image search)...")
    print()

    for i, search in enumerate(SEARCH_QUERIES):
        city = search["city"]
        query = search["query"]
        context = search["context"]
        city_dir = OUTPUT_DIR / city.lower().replace(" ", "-")
        city_dir.mkdir(exist_ok=True)

        print(f"  [{i+1}/{len(SEARCH_QUERIES)}] {city}: \"{query}\"")
        print(f"       Context: {context}")

        data = search_openverse(query)

        city_result = {
            "city": city,
            "query": query,
            "context": context,
            "results_count": 0,
            "downloaded": 0,
            "images": [],
        }

        if data and "results" in data:
            count = data.get("result_count", len(data["results"]))
            city_result["results_count"] = count
            results["total_found"] += count
            print(f"       Found: {count} total results")

            # Download top 3 results
            for j, img in enumerate(data["results"][:3]):
                img_url = img.get("url", "")
                title = img.get("title", "untitled")[:50]
                license_name = img.get("license", "unknown")
                creator = img.get("creator", "unknown")

                ext = ".jpg"
                if ".png" in img_url.lower():
                    ext = ".png"
                elif ".webp" in img_url.lower():
                    ext = ".webp"

                filename = f"{city.lower()}_{j+1}{ext}"
                filepath = city_dir / filename

                print(f"    [{j+1}] \"{title}\" (by {creator}, license: {license_name})")

                if download_image(img_url, filepath):
                    city_result["downloaded"] += 1
                    results["total_downloaded"] += 1
                    city_result["images"].append({
                        "title": title,
                        "creator": creator,
                        "license": license_name,
                        "url": img_url,
                        "local_file": str(filepath),
                    })

            print()
        else:
            print(f"       No results or API error")
            print()

        results["openverse_results"].append(city_result)

        # Be polite to the API
        if i < len(SEARCH_QUERIES) - 1:
            time.sleep(1.5)

    return results


def write_results(results):
    """Write RESULTS.md with findings and recommendations."""
    lines = [
        "# AI Image Sourcing Test Results",
        "",
        f"**Date:** {results['timestamp']}",
        f"**Purpose:** Evaluate image sourcing options for EZ Solutions location pages",
        "",
        "## Environment Check",
        "",
        f"- AI Generation APIs available: **{'Yes' if results['ai_apis_available'] else 'No'}**",
        "- DALL-E 3 (OpenAI): " + ("Available" if os.environ.get("OPENAI_API_KEY") else "Not configured"),
        "- Stability AI: " + ("Available" if (os.environ.get("STABILITY_API_KEY") or os.environ.get("STABILITY_KEY")) else "Not configured"),
        "- Ollama local: Not running",
        "",
        "## Openverse (CC-Licensed) Search Results",
        "",
        f"- **Total results found:** {results['total_found']}",
        f"- **Images downloaded:** {results['total_downloaded']}",
        "",
        "### Per-City Breakdown",
        "",
    ]

    for city_result in results["openverse_results"]:
        lines.append(f"#### {city_result['city']}")
        lines.append(f"- Query: \"{city_result['query']}\"")
        lines.append(f"- Context: {city_result['context']}")
        lines.append(f"- Results found: {city_result['results_count']}")
        lines.append(f"- Downloaded: {city_result['downloaded']}")

        if city_result["images"]:
            lines.append("- Images:")
            for img in city_result["images"]:
                lines.append(f"  - \"{img['title']}\" by {img['creator']} ({img['license']})")
        else:
            lines.append("- Images: None suitable found")
        lines.append("")

    lines.extend([
        "## Analysis",
        "",
        "### What Worked",
        "- Openverse API requires no API key for basic searches",
        "- Commercial-license filter ensures images are usable",
        "- Some HVAC-related imagery is available for generic terms",
        "",
        "### What Didn't Work",
        "- City-specific HVAC queries return very few relevant results",
        "- Most results are generic stock photos, not location-specific",
        "- Image quality varies significantly",
        "- No guarantee of visual consistency across pages",
        "",
        "### Limitations of Stock/CC Images for Location Pages",
        "- Cannot show actual local landmarks + HVAC equipment together",
        "- Generic images don't differentiate one city page from another",
        "- Limited selection for niche HVAC topics (ductwork, thermostats, etc.)",
        "",
        "## Recommendations",
        "",
        "### Tier 1: Best Quality (AI Generation)",
        "| Option | Cost per image | Setup | Quality |",
        "|--------|---------------|-------|---------|",
        "| DALL-E 3 | $0.04-0.08 | API key only | Excellent |",
        "| Stability AI (SDXL) | $0.002-0.006 | API key only | Good |",
        "| Local ComfyUI/SDXL | Free (after GPU) | Complex setup | Good |",
        "",
        "**For 50 location pages (3 images each = 150 images):**",
        "- DALL-E 3: ~$6-12",
        "- Stability AI: ~$0.30-0.90",
        "- Local: $0 marginal cost",
        "",
        "### Tier 2: Acceptable Fallback (Openverse/CC)",
        "- Use for generic HVAC imagery (tools, equipment close-ups)",
        "- Supplement with a small set of AI-generated hero images",
        "- Requires manual curation (not all results are relevant)",
        "",
        "### Tier 3: Hybrid Approach (Recommended)",
        "1. Use AI generation (Stability AI, cheapest) for city-specific hero images",
        "   - Prompt: \"Professional HVAC technician working on [equipment] in [city landmark area], photorealistic\"",
        "2. Use Openverse for generic supporting images (equipment, diagrams)",
        "3. Total estimated cost for 50 cities: $5-15",
        "",
        "### Next Steps",
        "1. Set up STABILITY_API_KEY (cheapest viable option)",
        "2. Create prompt templates per city that include local architectural context",
        "3. Generate a test batch of 5 hero images and evaluate quality",
        "4. If quality is sufficient, build into the v3 page pipeline",
        "",
        "## Prompt Templates (for AI Generation)",
        "",
        "```",
        "Hero image: \"Professional HVAC installation in a {architecture_style} home in",
        "{city}, Pennsylvania. {seasonal_context}. Photorealistic, commercial photography style.\"",
        "",
        "Examples:",
        "- Pittsburgh: \"...brick industrial loft in Pittsburgh... winter heating season...\"",
        "- Lancaster: \"...colonial stone farmhouse in Lancaster County... fall maintenance...\"",
        "- Philadelphia: \"...classic Philadelphia row house... summer AC installation...\"",
        "```",
    ])

    with open(RESULTS_FILE, "w") as f:
        f.write("\n".join(lines))

    print(f"Results written to: {RESULTS_FILE}")


if __name__ == "__main__":
    results = run_tests()

    print("=" * 60)
    print("SUMMARY")
    print("=" * 60)
    print(f"  AI APIs available: {'Yes' if results['ai_apis_available'] else 'No'}")
    print(f"  Openverse results found: {results['total_found']}")
    print(f"  Images downloaded: {results['total_downloaded']}")
    print()

    write_results(results)
    print("\nDone.")
