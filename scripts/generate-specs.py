#!/usr/bin/env python3
"""Generate 51 UNIQUE page architectures — no two pages share the same structural blueprint.
Uses combinatorial composition: each page gets a unique combination of hero, section flow,
special elements, band patterns, and density."""
import json, os, random
from itertools import product

random.seed(42)

CONTENT_DIR = "content"

# Approved Pencil designs own the visual structure for mapped pages. The
# combinatorial generator may vary content flow only inside that design family.
PENCIL_DESIGNS = {
    'Bethlehem, PA': {
        'design_family': 'bethlehem-split-industrial',
        'brand_palette': 'bethlehem',
        'layout_variant': 'services-first',
    },
}

all_entries = []
for fname in sorted(os.listdir(CONTENT_DIR)):
    if fname.startswith('v3-') and fname.endswith('.json'):
        with open(f'{CONTENT_DIR}/{fname}') as f:
            data = json.load(f)
        all_entries.extend(data)

print(f"Generating specs for {len(all_entries)} pages...")

# ============================================================================
# BUILDING BLOCKS — each dimension is varied independently
# ============================================================================

HEROES = ['hero_cover', 'hero_split', 'hero_text', 'hero_offset']

# Content section type pools — each "strategy" defines which types dominate
CONTENT_STRATEGIES = [
    ['content_media_left', 'content_media_right'],                    # 0: zigzag visual
    ['content_prose', 'content_indent'],                              # 1: editorial
    ['content_steps', 'content_timeline'],                            # 2: process
    ['content_wide_img', 'content_overlap'],                          # 3: image-heavy
    ['content_icon_list', 'content_steps'],                           # 4: list-driven
    ['content_prose', 'content_wide_img'],                            # 5: magazine
    ['content_indent', 'content_media_right'],                        # 6: indented visual
    ['content_media_left', 'content_overlap', 'content_steps'],       # 7: mixed premium
    ['content_timeline', 'content_indent'],                           # 8: timeline narrative
    ['content_wide_img', 'content_indent', 'content_prose'],          # 9: visual editorial
    ['content_overlap', 'content_media_left', 'content_prose'],       # 10: overlap-led
    ['content_media_right', 'content_wide_img', 'content_timeline'],  # 11: cinematic
    ['content_steps', 'content_overlap', 'content_media_right'],      # 12: process visual
]

# Where special elements go (relative position in the page)
SPECIAL_PLACEMENTS = [
    {'diagnostic': 'early', 'quote': 'mid', 'table': None, 'cards': None, 'stats': None, 'features': None},
    {'diagnostic': None, 'quote': 'early', 'table': 'mid', 'cards': None, 'stats': None, 'features': None},
    {'diagnostic': 'mid', 'quote': None, 'table': None, 'cards': 'early', 'stats': None, 'features': None},
    {'diagnostic': None, 'quote': 'late', 'table': 'early', 'cards': None, 'stats': None, 'features': None},
    {'diagnostic': 'early', 'quote': None, 'table': None, 'cards': None, 'stats': 'early', 'features': None},
    {'diagnostic': None, 'quote': 'early', 'table': None, 'cards': None, 'stats': None, 'features': 'mid'},
    {'diagnostic': None, 'quote': 'mid', 'table': None, 'cards': None, 'stats': 'early', 'features': None},
    {'diagnostic': 'late', 'quote': None, 'table': None, 'cards': None, 'stats': 'early', 'features': None},
    {'diagnostic': None, 'quote': None, 'table': 'mid', 'cards': None, 'stats': 'early', 'features': None},
    {'diagnostic': 'early', 'quote': 'late', 'table': None, 'cards': None, 'stats': None, 'features': 'mid'},
    {'diagnostic': None, 'quote': None, 'table': None, 'cards': 'mid', 'stats': 'early', 'features': None},
    {'diagnostic': None, 'quote': None, 'table': None, 'cards': None, 'stats': None, 'features': 'early'},
    {'diagnostic': None, 'quote': 'mid', 'table': None, 'cards': 'early', 'stats': None, 'features': None},
    {'diagnostic': 'mid', 'quote': None, 'table': None, 'cards': None, 'stats': None, 'features': 'early'},
    {'diagnostic': None, 'quote': None, 'table': None, 'cards': None, 'stats': 'early', 'features': 'mid'},
]

# CTA placement patterns
CTA_PATTERNS = [
    {'mid': 'cta_inline', 'final': 'cta_center', 'final_band': 'sand'},
    {'mid': 'cta_inline', 'final': 'cta_card', 'final_band': ''},
    {'mid': 'cta_inline', 'final': 'cta_center', 'final_band': 'ink'},
    {'mid': None, 'final': 'cta_center', 'final_band': 'sand'},
    {'mid': 'cta_inline', 'final': 'cta_fullbleed', 'final_band': ''},
    {'mid': None, 'final': 'cta_fullbleed', 'final_band': ''},
    {'mid': 'cta_inline', 'final': 'cta_center', 'final_band': 'dark-gradient'},
    {'mid': None, 'final': 'cta_card', 'final_band': ''},
]

# FAQ styles
FAQ_STYLES = ['', 'bordered', 'cards', 'numbered']

# FAQ placement
FAQ_POSITIONS = ['late', 'early', 'mid']

# Band pattern (which sections get background bands)
BAND_PATTERNS = [
    [],              # no bands (clean white)
    [2, 5],         # two sand bands
    [1, 4],         # early + late sand
    [3],            # single mid ink band
    [2, 4, 6],     # rhythmic bands
    [1, 3, 5],     # alternating
    [0, 4],        # bookend
    [3, 5],        # late cluster
]

# Band colors per pattern position
BAND_COLORS = ['sand', 'ink', 'sand', 'sand', 'ink', 'sand', 'sand']

# Quote variants
QUOTE_VARIANTS = ['accent', 'card', 'center', 'mark']

# Pills variants
PILLS_VARIANTS = ['', 'ember', 'outline']

# Page density (number of main content sections)
DENSITIES = [3, 4, 5, 6, 7, 8]

# ============================================================================
# COMPOSER — builds a unique page from a parameter set
# ============================================================================

def compose_page(entry, params):
    """Build a page from a unique parameter combination."""
    hero_type = HEROES[params['hero']]
    content_strat = CONTENT_STRATEGIES[params['content_strategy']]
    specials = SPECIAL_PLACEMENTS[params['special_placement']]
    cta_pat = CTA_PATTERNS[params['cta_pattern']]
    faq_style = FAQ_STYLES[params['faq_style']]
    faq_pos = FAQ_POSITIONS[params['faq_position']]
    band_pat = BAND_PATTERNS[params['band_pattern']]
    density = DENSITIES[params['density']]
    quote_var = QUOTE_VARIANTS[params['quote_variant']]
    pills_var = PILLS_VARIANTS[params['pills_variant']]

    secs = []
    content_idx = 0

    # --- HERO ---
    secs.append(make_hero(hero_type, entry))

    # --- Build the body ---
    # Calculate positions for specials
    total_slots = density + 2  # content sections + specials + local
    early_pos = 1
    mid_pos = max(2, density // 2)
    late_pos = density

    # FAQ early?
    if faq_pos == 'early':
        secs.append(make_faq(entry, faq_style))
        if cta_pat['mid']:
            secs.append(make_cta_mid(entry, cta_pat['mid']))

    # Early specials
    if specials['diagnostic'] == 'early' and entry.get('quick_check'):
        secs.append({'type': 'diagnostic', 'title': entry['quick_check']['title'], 'items': entry['quick_check']['items']})
    if specials.get('cards') == 'early' and len(entry['sections']) >= 3:
        secs.append(make_cards(entry, 0, params.get('card_variant', '')))
    if specials['table'] == 'early' and entry.get('key_facts'):
        secs.append(make_table(entry))
    if specials['quote'] == 'early' and entry.get('key_facts'):
        secs.append({'type': 'quote', 'text': entry['key_facts'][0], 'cite': entry['city'].replace(', PA',''), 'variant': quote_var})
    if specials.get('stats') == 'early' and entry.get('key_facts'):
        secs.append(make_stats(entry))
    if specials.get('features') == 'early' and len(entry['sections']) >= 3:
        secs.append(make_feature_row(entry, 0))

    # Content sections with mid-CTA and mid-specials woven in
    cta_placed = faq_pos == 'early'  # already placed if FAQ was early
    for i in range(density):
        sec_data = entry['sections'][content_idx % len(entry['sections'])]
        ct = content_strat[i % len(content_strat)]
        sec = make_content(sec_data, ct, entry)

        # Apply band?
        if i in band_pat:
            sec['band'] = BAND_COLORS[band_pat.index(i) % len(BAND_COLORS)]

        secs.append(sec)
        content_idx += 1

        # Mid-page CTA
        if i == mid_pos - 1 and cta_pat['mid'] and not cta_placed:
            secs.append(make_cta_mid(entry, cta_pat['mid']))
            cta_placed = True

        # Mid specials
        if i == mid_pos:
            if specials['diagnostic'] == 'mid' and entry.get('quick_check'):
                secs.append({'type': 'diagnostic', 'title': entry['quick_check']['title'], 'items': entry['quick_check']['items']})
            if specials.get('cards') == 'mid' and len(entry['sections']) >= 3:
                secs.append(make_cards(entry, 3, params.get('card_variant', '')))
            if specials['table'] == 'mid' and entry.get('key_facts'):
                secs.append(make_table(entry))
            if specials['quote'] == 'mid' and entry.get('key_facts'):
                secs.append({'type': 'quote', 'text': entry['key_facts'][0], 'cite': '', 'variant': quote_var})
            if specials.get('stats') == 'mid' and entry.get('key_facts'):
                secs.append(make_stats(entry))
            if specials.get('features') == 'mid' and len(entry['sections']) >= 3:
                secs.append(make_feature_row(entry, 3))

        # FAQ mid?
        if i == mid_pos and faq_pos == 'mid':
            secs.append(make_faq(entry, faq_style))

    # Late specials
    if specials['diagnostic'] == 'late' and entry.get('quick_check'):
        secs.append({'type': 'diagnostic', 'title': entry['quick_check']['title'], 'items': entry['quick_check']['items']})
    if specials['cards'] == 'late' and len(entry['sections']) >= 3:
        secs.append(make_cards(entry, 3, params.get('card_variant', '')))
    if specials['quote'] == 'late' and entry.get('key_facts') and len(entry['key_facts']) > 1:
        secs.append({'type': 'quote', 'text': entry['key_facts'][-1], 'cite': '', 'variant': quote_var})

    # Pills (key facts as tags)
    if entry.get('key_facts') and params.get('use_pills'):
        secs.append({'type': 'pills', 'items': entry['key_facts'][:5], 'variant': pills_var})

    # Local section
    local_types = ['content_prose', 'content_indent', 'content_media_left', 'content_media_right']
    local_ct = local_types[params['local_style'] % len(local_types)]
    local_band = ['sand', 'ink', '', 'sand'][params['local_style'] % 4]
    secs.append(make_local(entry, local_ct, local_band))

    # FAQ late?
    if faq_pos == 'late':
        secs.append(make_faq(entry, faq_style))

    # Mid-CTA fallback if never placed
    if not cta_placed and cta_pat['mid']:
        secs.append(make_cta_mid(entry, cta_pat['mid']))

    # Nearby
    secs.append(make_nearby(entry))

    # Final CTA
    secs.append(make_cta_final(entry, cta_pat['final'], cta_pat['final_band']))

    # Remove Nones
    secs = [s for s in secs if s is not None]
    return secs


# ============================================================================
# PARAMETER GENERATION — ensure all 51 are unique
# ============================================================================

def generate_unique_params(n):
    """Generate n unique parameter combinations with maximum spread."""
    params_list = []
    seen = set()

    # Key dimensions to vary
    hero_options = list(range(len(HEROES)))
    strat_options = list(range(len(CONTENT_STRATEGIES)))
    special_options = list(range(len(SPECIAL_PLACEMENTS)))
    cta_options = list(range(len(CTA_PATTERNS)))
    faq_style_options = list(range(len(FAQ_STYLES)))
    faq_pos_options = list(range(len(FAQ_POSITIONS)))
    band_options = list(range(len(BAND_PATTERNS)))
    density_options = list(range(len(DENSITIES)))
    quote_options = list(range(len(QUOTE_VARIANTS)))
    pills_options = list(range(len(PILLS_VARIANTS)))

    attempts = 0
    while len(params_list) < n and attempts < 10000:
        attempts += 1
        p = {
            'hero': random.choice(hero_options),
            'content_strategy': random.choice(strat_options),
            'special_placement': random.choice(special_options),
            'cta_pattern': random.choice(cta_options),
            'faq_style': random.choice(faq_style_options),
            'faq_position': random.choice(faq_pos_options),
            'band_pattern': random.choice(band_options),
            'density': random.choice(density_options),
            'quote_variant': random.choice(quote_options),
            'pills_variant': random.choice(pills_options),
            'use_pills': random.random() > 0.4,
            'local_style': random.randint(0, 3),
            'card_variant': random.choice(['', 'accent', '']),
        }
        # Create a signature from the structural dimensions
        sig = (p['hero'], p['content_strategy'], p['special_placement'],
               p['cta_pattern'], p['faq_position'], p['band_pattern'], p['density'])
        if sig not in seen:
            seen.add(sig)
            params_list.append(p)

    # If we didn't get enough (unlikely), relax uniqueness
    while len(params_list) < n:
        p = {
            'hero': random.choice(hero_options),
            'content_strategy': random.choice(strat_options),
            'special_placement': random.choice(special_options),
            'cta_pattern': random.choice(cta_options),
            'faq_style': random.choice(faq_style_options),
            'faq_position': random.choice(faq_pos_options),
            'band_pattern': random.choice(band_options),
            'density': random.choice(density_options),
            'quote_variant': random.choice(quote_options),
            'pills_variant': random.choice(pills_options),
            'use_pills': random.random() > 0.4,
            'local_style': random.randint(0, 3),
            'card_variant': random.choice(['', 'accent', '']),
        }
        params_list.append(p)

    return params_list


# ============================================================================
# HELPERS
# ============================================================================

def make_hero(hero_type, entry):
    sec = {'type': hero_type, 'headline': entry['hook'], 'image_topic': entry['hero_topic']}
    if hero_type == 'hero_cover':
        sec['subline'] = entry['intro'][0][:120] if entry['intro'] else ''
        sec['cta'] = entry['closing']['cta']
    elif hero_type == 'hero_split':
        sec['text'] = entry['intro'][0] if entry['intro'] else ''
        sec['cta'] = entry['closing']['cta']
    elif hero_type == 'hero_text':
        sec['subline'] = entry['intro'][0][:150] if entry['intro'] else ''
        sec['cta'] = entry['closing']['cta']
    elif hero_type == 'hero_offset':
        sec['text'] = entry['intro'][0][:200] if entry['intro'] else ''
        sec['cta'] = entry['closing']['cta']
    return sec

def make_content(s, ct, entry):
    sec = {'type': ct, 'heading': s['title'], 'paras': s['paras']}
    if ct in ('content_media_left', 'content_media_right', 'content_wide_img'):
        sec['image_topic'] = s.get('image_topic', entry['hero_topic'])
    if ct in ('content_steps', 'content_timeline'):
        sec['items'] = s.get('list', s['paras'][:3])
    if ct == 'content_icon_list':
        sec['list'] = s.get('list', s['paras'][:3])
    return sec

def make_cta_mid(entry, cta_type='cta_inline'):
    if cta_type == 'cta_inline':
        return {'type': 'cta_inline', 'text': entry.get('mid_cta', entry['closing']['para'][:100]), 'link_text': entry.get('mid_cta_link', 'Schedule a visit')}
    return {'type': 'cta_inline', 'text': entry.get('mid_cta', entry['closing']['para'][:100]), 'link_text': entry.get('mid_cta_link', 'Schedule a visit')}

def make_cards(entry, start_idx, variant=''):
    sections = entry['sections'][start_idx:start_idx+3]
    if not sections:
        sections = entry['sections'][:3]
    cards = [{'title': s['title'], 'text': ' '.join(s['paras']), 'list': s.get('list')} for s in sections]
    return {'type': 'cards', 'heading': 'What we offer', 'cards': cards, 'cols': min(3, len(cards)), 'variant': variant}

def make_stats(entry):
    """Build stat items from key_facts — extract numbers or create abbreviated stats."""
    items = []
    for fact in entry.get('key_facts', [])[:4]:
        # Try to extract a number from the fact
        import re
        nums = re.findall(r'(\d+[°%+]?|\$[\d,]+|[\d.]+ (?:yrs?|hours?|days?))', fact)
        if nums:
            items.append({'number': nums[0], 'label': fact[:40]})
        else:
            words = fact.split()
            items.append({'number': words[0][:6] if words else '—', 'label': ' '.join(words[1:6])})
    if not items:
        return None
    return {'type': 'stats', 'items': items}

def make_feature_row(entry, start_idx):
    """Build a feature row from section titles."""
    sections = entry['sections'][start_idx:start_idx+3]
    if not sections:
        sections = entry['sections'][:3]
    features = [{'title': s['title'], 'text': s['paras'][0][:100] if s['paras'] else ''} for s in sections]
    return {'type': 'feature_row', 'heading': 'Our Services', 'features': features}

def make_table(entry):
    rows = []
    for fact in entry.get('key_facts', [])[:6]:
        parts = fact.split(': ', 1) if ': ' in fact else fact.split(' — ', 1)
        rows.append(parts if len(parts) == 2 else [fact, '—'])
    if not rows:
        return None
    return {'type': 'table', 'heading': 'At a glance', 'headers': ['Detail', 'Info'], 'rows': rows, 'variant': random.choice(['striped', 'compact', '', 'clean'])}

def make_local(entry, ct, band):
    if not entry.get('local'):
        return None
    sec = {'type': ct, 'heading': entry['local']['title'], 'paras': entry['local']['paras']}
    if band:
        sec['band'] = band
    if ct in ('content_media_left', 'content_media_right'):
        sec['image_topic'] = entry['hero_topic']
    return sec

def make_faq(entry, variant):
    if not entry.get('faq'):
        return None
    return {'type': 'faq', 'heading': entry.get('faq_heading', 'Frequently Asked Questions'), 'items': entry['faq'], 'variant': variant}

def make_cta_final(entry, cta_type, band):
    c = entry['closing']
    if cta_type == 'cta_center':
        return {'type': 'cta_center', 'heading': c['title'], 'text': c['para'], 'cta': c['cta'], 'band': band}
    elif cta_type == 'cta_card':
        return {'type': 'cta_card', 'heading': c['title'], 'text': c['para'], 'cta': c['cta']}
    elif cta_type == 'cta_fullbleed':
        return {'type': 'cta_fullbleed', 'heading': c['title'], 'text': c['para'], 'cta': c['cta']}
    else:
        return {'type': 'cta_inline', 'text': c['para'][:150], 'link_text': c['cta']}

def make_nearby(entry):
    if not entry.get('nearby'):
        return None
    return {'type': 'nearby', 'cities': entry['nearby']}


# ============================================================================
# MAIN — generate 51 unique compositions
# ============================================================================

all_params = generate_unique_params(len(all_entries))

# Shuffle and assign
random.shuffle(all_params)

specs = []
for idx, entry in enumerate(all_entries):
    params = all_params[idx]
    page_sections = compose_page(entry, params)

    # Build a descriptive layout name from key params
    hero_name = HEROES[params['hero']].replace('hero_', '')
    strat_names = ['zigzag','editorial','process','imgHeavy','listDriven','magazine','indentVisual','mixedPrem','timelineNar','visualEd','overlapLed','cinematic','processVis']
    strat_name = strat_names[params['content_strategy'] % len(strat_names)]
    density_val = DENSITIES[params['density']]
    layout_name = f"{hero_name}_{strat_name}_d{density_val}"

    spec = {
        'city': entry['city'],
        'meta_description': entry.get('meta_description', ''),
        'layout_type': layout_name,
        'sections': page_sections,
    }
    spec.update(PENCIL_DESIGNS.get(entry['city'], {}))
    specs.append(spec)

# Write specs
os.makedirs('content/specs', exist_ok=True)
for f in os.listdir('content/specs'):
    os.remove(f'content/specs/{f}')

for i in range(0, len(specs), 10):
    batch = specs[i:i+10]
    fname = f'content/specs/layout-{i//10}.json'
    with open(fname, 'w') as f:
        json.dump(batch, f, indent=1, ensure_ascii=False)
    print(f"  Wrote {fname} ({len(batch)} pages)")

# Diversity report
print(f"\n=== DIVERSITY REPORT ===")
layouts = [s['layout_type'] for s in specs]
unique_layouts = len(set(layouts))
print(f"Unique layout signatures: {unique_layouts}/{len(specs)}")

# Hero distribution
from collections import Counter
hero_dist = Counter()
for s in specs:
    hero_dist[s['sections'][0].get('type', '?')] += 1
print(f"\nHero distribution:")
for h, c in hero_dist.most_common():
    print(f"  {h}: {c}")

# Section type usage
section_counts = Counter()
for spec in specs:
    for sec in spec['sections']:
        section_counts[sec['type']] += 1
print(f"\nSection type usage (top 15):")
for t, c in section_counts.most_common(15):
    print(f"  {t}: {c}")

# Page lengths
lengths = [len(s['sections']) for s in specs]
print(f"\nPage lengths: min={min(lengths)}, max={max(lengths)}, avg={sum(lengths)/len(lengths):.1f}")
print(f"Length distribution: {Counter(lengths).most_common()}")

# Content strategy distribution
strat_dist = Counter()
for p in all_params[:len(all_entries)]:
    strat_dist[p['content_strategy']] += 1
print(f"\nContent strategies used: {len(strat_dist)}/10")

# Band pattern distribution
band_dist = Counter()
for p in all_params[:len(all_entries)]:
    band_dist[p['band_pattern']] += 1
print(f"Band patterns used: {len(band_dist)}/8")
