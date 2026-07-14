#!/usr/bin/env python3
"""Agentic spec generator: one Claude call per page, each with a unique creative brief.
Agents are isolated — they cannot see each other's output, preventing pattern copying."""

import json, os, sys, asyncio, time
from pathlib import Path

try:
    import anthropic
except ImportError:
    print("pip install anthropic")
    sys.exit(1)

CONTENT_DIR = "content"
BRIEFS_FILE = "scripts/briefs.json"
OUTPUT_DIR = "content/specs"
MODEL = "claude-haiku-4-5-20251001"
MAX_CONCURRENT = 8

# Load all content entries
all_entries = []
for fname in sorted(os.listdir(CONTENT_DIR)):
    if fname.startswith('v3-') and fname.endswith('.json'):
        with open(f'{CONTENT_DIR}/{fname}') as f:
            data = json.load(f)
        all_entries.extend(data)

print(f"Loaded {len(all_entries)} content entries")

# Load creative briefs
with open(BRIEFS_FILE) as f:
    briefs = json.load(f)

assert len(briefs) >= len(all_entries), f"Need {len(all_entries)} briefs, have {len(briefs)}"

SECTION_CATALOG = """
AVAILABLE SECTION TYPES (these are what v3-render.php supports):

HEROES (pick exactly one, it goes first):
- hero_cover: Full-viewport background image, headline overlay, Ken Burns animation. Fields: headline, subline, image_topic, cta
- hero_split: 50/50 image left + text right. Fields: headline, text, image_topic, cta
- hero_text: Text-only, centered, no image. Fields: headline, subline, cta
- hero_offset: Background image with floating white card overlay. Fields: headline, text, image_topic, cta

CONTENT SECTIONS (the body of the page):
- content_prose: Simple text column (narrow, readable). Fields: heading, paras[]
- content_media_left: Image left, text right (media-text block). Fields: heading, paras[], image_topic, list[]?
- content_media_right: Text left, image right. Fields: heading, paras[], image_topic, list[]?
- content_wide_img: Full-width image above text. Fields: heading, paras[], image_topic
- content_indent: Left-bordered emphasis block. Fields: heading, paras[]
- content_steps: Numbered step list with gradient circles. Fields: heading, items[]
- content_icon_list: Arrow-prefixed feature list. Fields: heading, paras[], list[]
- content_timeline: Vertical timeline with dots. Fields: heading, items[]
- content_overlap: Large image with floating card overlapping it. Fields: heading, paras[], image_topic

STRUCTURED DATA:
- stats: Big numbers in a grid (impressive metrics). Fields: items[{number, label}]
- cards: Card grid (2-3 cols, hover-lift). Fields: heading, cards[{title, text}], cols(2|3), variant('' or 'accent')
- feature_row: Icon-centered service cards with flip animation. Fields: heading, features[{title, text}]
- table: Data table with striped/clean/modern/compact variants. Fields: heading, headers[], rows[][], variant
- pills: Rounded tag chips (key facts). Fields: items[], variant('' or 'ember' or 'outline' or 'glass')

QUOTES:
- quote: Pullquote with variants. Fields: text, cite, variant(accent|card|center|mark|dramatic)

CTA (calls to action):
- cta_center: Centered CTA with background band. Fields: heading, text, cta, band(sand|ink|dark-gradient)
- cta_inline: Compact inline CTA bar. Fields: text, link_text
- cta_card: Elevated card CTA with shadow. Fields: heading, text, cta
- cta_fullbleed: Full-width dark gradient CTA. Fields: heading, text, cta

UTILITY:
- faq: Accordion FAQ. Fields: heading, items[{q, a}], variant('' or 'bordered' or 'cards' or 'numbered' or 'minimal')
- diagnostic: Warning-topped checklist. Fields: title, items[]
- warning: Red-bordered alert box. Fields: title, items[]
- nearby: Linked city chips. Fields: cities[]
- separator: Visual break. Fields: variant('' or 'wide' or 'dot')

BANDS (optional background color on any section):
Add "band" field to any section: "sand", "ink", "cream", "warm-gradient", "dark-gradient", "ember", "gradient"
Dark bands (ink, dark-gradient, gradient) make text white automatically.

IMAGE TOPICS AVAILABLE: technician, furnace, ductwork, thermostat, heatpump, minisplit, radiator, basement, condenser, filter, rowhouse, suburban, commercial
"""

GATE_REQUIREMENTS = """
GATE REQUIREMENTS (your spec MUST pass these):
1. Page must render ≥750 words total (aim for 900+)
2. Must have ≥2 sections with image_topic (inline images)
3. Must have ≥4 h2 headings (each content section with a "heading" counts)
4. Paragraphs must be ≤50 words each (3 lines max in full-width layout)
5. Must include meta_description
6. Must include nearby[] cities
7. The FIRST paragraph on the page must start with a UNIQUE opening (first 3 words matter)
8. Content must not be generic — use the city's specific details

LAYOUT QUALITY RULES (these prevent bland pages):
9. At LEAST 3 sections must have a "band" field (use sand, ink, cream, warm-gradient, dark-gradient to break up the white)
10. You MUST include at least ONE of: cards, feature_row, stats, or table (structured visual element)
11. Do NOT use content_prose more than twice — use media variants, steps, icon_list, indent, overlap instead
12. You MUST include a service list — either as a feature_row, cards, icon_list, or content_steps section that clearly lists what services are offered (repairs, installation, maintenance, emergency, etc.)
13. Vary section widths — don't make every section the same narrow column. Mix wide images, overlaps, media-text blocks, and full-bleed bands.

DESIGN TASTE (these are non-negotiable):
14. Hero HEADLINE must be ≤10 words. It's displayed at 4.5rem font centered on a full-viewport image. Long sentences look like garbage. Write a SHORT punchy headline (e.g. "Your rowhouse deserves better" or "Comfort without compromise"). If the hook from the data is too long, shorten it or write a new one — put the full hook in the "subline" field instead.
15. CTA button text must be ≤5 words. Just the action: "Get an Assessment", "Schedule a Visit", "Call Now", "Book the Walkthrough". NEVER include hashtags, URLs, anchor fragments, technical terms, or explanations in button text.
16. Subline/lead text (shown small above or below headline) should be 1 short sentence, max 15 words. It's a teaser, not a paragraph.
17. Section headings should be 3-8 words. They're h2s at 2.2rem. Not full sentences.
18. Quote text should be 1-2 sentences max. Pullquotes are meant to be glanceable.
19. Card titles should be 2-5 words. Cards are scannable.
20. Stat numbers must be ACTUAL numbers or short values: "15+", "$2,400", "30°F", "R-38". Not words, not sentences.

EZ BEST PRACTICES (from EZMarketing's official checklist — follow ALL):
21. STORYBRAND COPY: Customer is the HERO, business is the GUIDE. Frame as: customer has problem → business provides plan → customer succeeds. NEVER lead with "we are" or "our company" — lead with the customer's pain.
22. H1 must make the reader say "That's me!" — speak to their situation, not your features.
23. Every section stands on its own. No section depends on another to make sense.
24. Content focused on the CLIENT'S PROBLEM first, then the solution. Features come after pain.
25. NO "click here" CTAs ever. Describe the action/outcome.
26. NO email addresses in content. Use forms.
27. Every section MUST have a visual design element — no plain-text-only sections. Each needs: image, colored background, cards/boxes, icon list, or similar.
28. NO white text on dark background for body paragraphs (OK for hero overlays and short CTA bands only).
29. Photos must support the message — no generic stock. People should look engaged in relevant activity.
30. Avoid full-image darken overlays when possible — prefer floating cards/containers over images.
"""


def build_prompt(entry, brief):
    content_json = json.dumps(entry, indent=2, ensure_ascii=False)
    return f"""You are a premium web page architect. Design a UNIQUE page layout spec for this city's HVAC service page.

YOUR CREATIVE BRIEF (this defines YOUR page's personality — commit fully to it):
Style: {brief['style']}
Directive: {brief['directive']}

{SECTION_CATALOG}

{GATE_REQUIREMENTS}

CITY CONTENT DATA (use this content — rearrange, select from, and structure it according to your brief):
{content_json}

INSTRUCTIONS:
1. Study your creative brief. Your page must FEEL fundamentally different from a generic template.
2. Select sections from the catalog that serve your brief's vision.
3. Arrange them in a compelling order that matches your brief's directive.
4. Pull text content from the city data — you may split paragraphs, reorder sections, use intros as hero text, etc.
5. Write a SHORT hero headline (≤10 words) inspired by the entry's hook — do NOT copy the full hook verbatim. The hook is a full sentence; your headline must be a punchy fragment. Put the full hook as the subline/text field instead.
6. Ensure at least 2 sections have image_topic fields.
7. Ensure at least 4 sections have heading fields (for h2 count).
8. Include FAQ, nearby, and a final CTA.

SELF-SCORE: Before outputting, score your spec 0-10 on each criterion below. Include the scorecard in your JSON output. ANY item below 7 means you must revise before outputting.

OUTPUT FORMAT — respond with ONLY a JSON object (no markdown, no explanation):
{{
  "city": "{entry['city']}",
  "meta_description": "...",
  "layout_type": "{brief['style']}",
  "scorecard": {{
    "storybrand_customer_hero": 0,
    "headline_thats_me": 0,
    "paragraph_brevity": 0,
    "cta_quality": 0,
    "visual_every_section": 0,
    "color_contrast": 0,
    "section_independence": 0,
    "service_list_present": 0,
    "image_variety": 0,
    "layout_diversity": 0,
    "no_dark_body_text": 0,
    "word_count_target": 0,
    "mobile_safe": 0,
    "design_taste": 0
  }},
  "sections": [
    {{"type": "hero_cover", "headline": "...", ...}},
    ...
  ]
}}

SCORECARD CRITERIA (score each 0-10):
- storybrand_customer_hero: Is the customer framed as hero, business as guide? (10=perfect StoryBrand, 0=all about the company)
- headline_thats_me: Does H1 make the reader say "That's me!"? (10=nails their pain, 0=generic company description)
- paragraph_brevity: Are ALL paragraphs ≤50 words / 3 lines? (10=all short, 0=walls of text)
- cta_quality: Are CTAs specific action words, no "click here"/"learn more"? (10=compelling specific, 0=generic)
- visual_every_section: Does EVERY section have a design element (image, bg, cards, icons)? (10=all have visuals, 0=plain text sections)
- color_contrast: Are band colors high-contrast and purposeful? (10=varied and readable, 0=monotone)
- section_independence: Can each section stand alone without context? (10=fully independent, 0=depends on prior section)
- service_list_present: Is there a clear structured service listing? (10=scannable service section, 0=services buried in prose)
- image_variety: Multiple image topics used, not repetitive? (10=diverse imagery, 0=same topic repeated)
- layout_diversity: Mix of section widths and types? (10=varied layout, 0=all same narrow columns)
- no_dark_body_text: No white-on-dark for body paragraphs? (10=follows rule, 0=violates)
- word_count_target: Will rendered page hit 750+ words? (10=comfortably above, 0=way short)
- mobile_safe: Will layout work on 375px without breaking? (10=fully safe, 0=will break)
- design_taste: Headlines short, CTAs clean, stats are numbers, cards scannable? (10=polished, 0=messy)"""


async def generate_spec(client, entry, brief, semaphore, idx, attempt=1):
    """Generate a spec for one city using one isolated agent call."""
    async with semaphore:
        city = entry['city']
        print(f"  [{idx+1:2d}/51] Generating: {city} (style: {brief['style']})" + (f" [retry {attempt}]" if attempt > 1 else ""))

        prompt = build_prompt(entry, brief)

        try:
            message = await asyncio.to_thread(
                client.messages.create,
                model=MODEL,
                max_tokens=8192,
                temperature=1.0,
                messages=[{"role": "user", "content": prompt}]
            )

            text = message.content[0].text.strip()
            if text.startswith("```"):
                text = text.split("\n", 1)[1]
                if text.endswith("```"):
                    text = text[:-3]
                elif "```" in text:
                    text = text[:text.rfind("```")]
            text = text.strip()

            spec = json.loads(text)

            sections = spec.get('sections', [])
            img_count = sum(1 for s in sections if s.get('image_topic'))
            h2_count = sum(1 for s in sections if s.get('heading') or s.get('headline'))

            if img_count < 2:
                print(f"    ⚠ {city}: only {img_count} images, needs ≥2")
            if h2_count < 4:
                print(f"    ⚠ {city}: only {h2_count} headings, needs ≥4")
            if len(sections) < 6:
                print(f"    ⚠ {city}: only {len(sections)} sections (short page)")

            # Scorecard validation
            scorecard = spec.get('scorecard', {})
            if scorecard:
                scores = list(scorecard.values())
                avg_score = sum(scores) / len(scores) if scores else 0
                low_items = [k for k, v in scorecard.items() if isinstance(v, (int, float)) and v < 7]
                if low_items:
                    print(f"    ⚠ {city}: low scores ({avg_score:.1f} avg) — {', '.join(low_items)}")
                    if avg_score < 6:
                        print(f"    ✗ {city}: REJECTED (avg score {avg_score:.1f} < 6)")
                        return None
                print(f"    ✓ {city}: {len(sections)} sections, {img_count} imgs, {h2_count} h2s, score {avg_score:.1f}/10")
            else:
                print(f"    ⚠ {city}: no scorecard returned")
                print(f"    ✓ {city}: {len(sections)} sections, {img_count} imgs, {h2_count} h2s")

            return spec

        except json.JSONDecodeError as e:
            print(f"    ✗ {city}: bad JSON — {e}")
            return None
        except Exception as e:
            print(f"    ✗ {city}: API error — {e}")
            return None


async def main():
    client = anthropic.Anthropic()
    semaphore = asyncio.Semaphore(MAX_CONCURRENT)

    print(f"\nLaunching {len(all_entries)} independent agent calls (max {MAX_CONCURRENT} concurrent)...")
    print(f"Model: {MODEL}, Temperature: 1.0 (max diversity)\n")

    start = time.time()

    tasks = [
        generate_spec(client, entry, briefs[idx], semaphore, idx)
        for idx, entry in enumerate(all_entries)
    ]

    results = await asyncio.gather(*tasks)

    # Retry failed ones (up to 2 retries)
    for retry_round in range(1, 3):
        failed_indices = [i for i, r in enumerate(results) if r is None]
        if not failed_indices:
            break
        print(f"\n--- Retry round {retry_round}: {len(failed_indices)} failed pages ---")
        retry_tasks = [
            generate_spec(client, all_entries[i], briefs[i], semaphore, i, retry_round + 1)
            for i in failed_indices
        ]
        retry_results = await asyncio.gather(*retry_tasks)
        for i, res in zip(failed_indices, retry_results):
            if res is not None:
                results[i] = res

    elapsed = time.time() - start
    print(f"\n{'='*60}")
    print(f"Completed in {elapsed:.1f}s")

    specs = [r for r in results if r is not None]
    failed = len(results) - len(specs)
    print(f"Success: {len(specs)}/51, Failed: {failed}")

    if failed > 0:
        print("\nFailed cities:")
        for i, r in enumerate(results):
            if r is None:
                print(f"  - {all_entries[i]['city']}")

    if not specs:
        print("No specs generated. Exiting.")
        return

    # Write output
    os.makedirs(OUTPUT_DIR, exist_ok=True)
    # Clear old specs
    for f in os.listdir(OUTPUT_DIR):
        if f.endswith('.json'):
            os.remove(f'{OUTPUT_DIR}/{f}')

    # Write in batches of 10
    for i in range(0, len(specs), 10):
        batch = specs[i:i+10]
        fname = f'{OUTPUT_DIR}/layout-{i//10}.json'
        with open(fname, 'w') as f:
            json.dump(batch, f, indent=1, ensure_ascii=False)
        print(f"  Wrote {fname} ({len(batch)} pages)")

    # Diversity report
    print(f"\n{'='*60}")
    print("DIVERSITY REPORT")
    print(f"{'='*60}")

    from collections import Counter

    # Hero distribution
    hero_dist = Counter()
    for s in specs:
        if s['sections']:
            hero_dist[s['sections'][0].get('type', '?')] += 1
    print(f"\nHero types: {dict(hero_dist)}")

    # Section type usage
    section_types = Counter()
    for spec in specs:
        for sec in spec['sections']:
            section_types[sec['type']] += 1
    print(f"\nSection types used: {len(section_types)}")
    for t, c in section_types.most_common():
        print(f"  {t}: {c}")

    # Page lengths
    lengths = [len(s['sections']) for s in specs]
    print(f"\nPage lengths: min={min(lengths)}, max={max(lengths)}, avg={sum(lengths)/len(lengths):.1f}")

    # Layout type uniqueness
    layout_types = [s.get('layout_type', '') for s in specs]
    print(f"Unique layout types: {len(set(layout_types))}/{len(specs)}")

    # First-word uniqueness (opening frames)
    openings = []
    for spec in specs:
        for sec in spec['sections']:
            if sec['type'] in ('content_prose', 'content_media_left', 'content_media_right',
                               'content_indent', 'content_wide_img', 'content_overlap'):
                paras = sec.get('paras', [])
                if paras:
                    words = paras[0].split()[:3]
                    openings.append(' '.join(words))
                    break
    dupe_openings = [o for o, c in Counter(openings).items() if c > 1]
    if dupe_openings:
        print(f"\n⚠ Duplicate opening frames: {dupe_openings}")
    else:
        print(f"\n✓ All opening frames unique")

    # Scorecard aggregate report
    print(f"\n{'='*60}")
    print("SCORECARD REPORT")
    print(f"{'='*60}")

    all_scorecards = [s.get('scorecard', {}) for s in specs if s.get('scorecard')]
    if all_scorecards:
        criteria = list(all_scorecards[0].keys())
        print(f"\n{'Criterion':<30} {'Avg':>5} {'Min':>5} {'<7':>5}")
        print("-" * 50)
        for criterion in criteria:
            values = [sc.get(criterion, 0) for sc in all_scorecards if isinstance(sc.get(criterion), (int, float))]
            if values:
                avg = sum(values) / len(values)
                lo = min(values)
                below7 = sum(1 for v in values if v < 7)
                flag = " ⚠" if below7 > 0 else ""
                print(f"  {criterion:<28} {avg:>5.1f} {lo:>5} {below7:>5}{flag}")

        all_avgs = []
        for sc in all_scorecards:
            vals = [v for v in sc.values() if isinstance(v, (int, float))]
            if vals:
                all_avgs.append(sum(vals) / len(vals))
        if all_avgs:
            print(f"\n  Overall avg: {sum(all_avgs)/len(all_avgs):.1f}/10")
            print(f"  Lowest page avg: {min(all_avgs):.1f}/10")
            print(f"  Highest page avg: {max(all_avgs):.1f}/10")
            below_threshold = sum(1 for a in all_avgs if a < 7)
            if below_threshold:
                print(f"  ⚠ {below_threshold} pages scored below 7.0 average")
    else:
        print("\n  No scorecards returned by agents")


if __name__ == '__main__':
    asyncio.run(main())
