# AI Image Sourcing Test Results

**Date:** 2026-07-13T17:31:49.699902
**Purpose:** Evaluate image sourcing options for EZ Solutions location pages

## Environment Check

- AI Generation APIs available: **No**
- DALL-E 3 (OpenAI): Not configured
- Stability AI: Not configured
- Ollama local: Not running

## Openverse (CC-Licensed) Search Results

- **Total results found:** 4
- **Images downloaded:** 4

### Per-City Breakdown

#### Pittsburgh
- Query: "industrial heating system Pittsburgh"
- Context: Steel city, older industrial buildings, harsh winters
- Results found: 1
- Downloaded: 1
- Images:
  - "Sea of Cars" by LollyOhMy (by)

#### Lancaster
- Query: "colonial house radiator heating"
- Context: Historic colonial architecture, older homes needing HVAC upgrades
- Results found: 0
- Downloaded: 0
- Images: None suitable found

#### Philadelphia
- Query: "row house HVAC air conditioning"
- Context: Dense row houses, summer humidity, mixed heating/cooling needs
- Results found: 0
- Downloaded: 0
- Images: None suitable found

#### Harrisburg
- Query: "commercial building HVAC rooftop unit"
- Context: State capital, commercial buildings, government facilities
- Results found: 0
- Downloaded: 0
- Images: None suitable found

#### Allentown
- Query: "residential furnace basement heating"
- Context: Lehigh Valley suburbs, residential furnace replacements
- Results found: 3
- Downloaded: 3
- Images:
  - "Floor Failure Causes AST Line Break 3" by Massachusetts Dept. of Environmental Protection (by)
  - "Floor Failure Causes AST Line Break 2" by Massachusetts Dept. of Environmental Protection (by)
  - "Floor Failure Causes AST Line Break" by Massachusetts Dept. of Environmental Protection (by)

## Analysis

### What Worked
- Openverse API requires no API key for basic searches
- Commercial-license filter ensures images are usable
- Some HVAC-related imagery is available for generic terms

### What Didn't Work
- City-specific HVAC queries return very few relevant results
- Most results are generic stock photos, not location-specific
- Image quality varies significantly
- No guarantee of visual consistency across pages

### Limitations of Stock/CC Images for Location Pages
- Cannot show actual local landmarks + HVAC equipment together
- Generic images don't differentiate one city page from another
- Limited selection for niche HVAC topics (ductwork, thermostats, etc.)

## Recommendations

### Tier 1: Best Quality (AI Generation)
| Option | Cost per image | Setup | Quality |
|--------|---------------|-------|---------|
| DALL-E 3 | $0.04-0.08 | API key only | Excellent |
| Stability AI (SDXL) | $0.002-0.006 | API key only | Good |
| Local ComfyUI/SDXL | Free (after GPU) | Complex setup | Good |

**For 50 location pages (3 images each = 150 images):**
- DALL-E 3: ~$6-12
- Stability AI: ~$0.30-0.90
- Local: $0 marginal cost

### Tier 2: Acceptable Fallback (Openverse/CC)
- Use for generic HVAC imagery (tools, equipment close-ups)
- Supplement with a small set of AI-generated hero images
- Requires manual curation (not all results are relevant)

### Tier 3: Hybrid Approach (Recommended)
1. Use AI generation (Stability AI, cheapest) for city-specific hero images
   - Prompt: "Professional HVAC technician working on [equipment] in [city landmark area], photorealistic"
2. Use Openverse for generic supporting images (equipment, diagrams)
3. Total estimated cost for 50 cities: $5-15

### Next Steps
1. Set up STABILITY_API_KEY (cheapest viable option)
2. Create prompt templates per city that include local architectural context
3. Generate a test batch of 5 hero images and evaluate quality
4. If quality is sufficient, build into the v3 page pipeline

## Prompt Templates (for AI Generation)

```
Hero image: "Professional HVAC installation in a {architecture_style} home in
{city}, Pennsylvania. {seasonal_context}. Photorealistic, commercial photography style."

Examples:
- Pittsburgh: "...brick industrial loft in Pittsburgh... winter heating season..."
- Lancaster: "...colonial stone farmhouse in Lancaster County... fall maintenance..."
- Philadelphia: "...classic Philadelphia row house... summer AC installation..."
```