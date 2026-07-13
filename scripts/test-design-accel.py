#!/usr/bin/env python3
"""
Design Acceleration Test
========================
Tests whether Claude can produce detailed visual specifications from a creative
brief alone -- replacing or speeding up the Photoshop comp stage.

Generates:
  - Full JSON design spec per client brief
  - CSS variables file per spec
  - Saves everything to content/design-specs/
"""

import json
import os
import sys
import time
from pathlib import Path

import anthropic

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

MODEL = "claude-haiku-4-5-20251001"
OUTPUT_DIR = Path(__file__).resolve().parent.parent / "content" / "design-specs"
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

CLIENT_BRIEFS = [
    {
        "id": "hvac-comfort-air",
        "industry": "HVAC",
        "business_name": "Comfort Air Solutions",
        "brief": (
            "We're a family-owned HVAC company in the Midwest. Our brand is "
            "trustworthy, dependable, and warm -- think a reliable neighbor who "
            "always shows up on time. We want to feel professional but approachable, "
            "not corporate or cold."
        ),
    },
    {
        "id": "law-firm-sterling",
        "industry": "Law Firm",
        "business_name": "Sterling & Associates",
        "brief": (
            "We're a boutique personal injury law firm. Our brand is authoritative "
            "yet compassionate -- clients are going through hard times and need to "
            "feel both protected and understood. Premium feel without being stuffy. "
            "Modern professionalism."
        ),
    },
    {
        "id": "dental-bright-smile",
        "industry": "Dental Practice",
        "business_name": "Bright Smile Family Dentistry",
        "brief": (
            "We're a modern family dental practice that treats patients of all ages. "
            "Our brand is clean, friendly, and calming -- we want to reduce dental "
            "anxiety. Think spa-like tranquility meets clinical confidence. Bright "
            "and airy, never sterile or intimidating."
        ),
    },
]

SYSTEM_PROMPT = """\
You are a senior web designer producing detailed visual specifications for small \
business websites. You output ONLY valid JSON -- no markdown, no commentary.

Your specs must be detailed enough that a front-end developer could build the \
page WITHOUT a Photoshop comp or any other visual reference.

Output a single JSON object with this exact structure:
{
  "business_name": "...",
  "industry": "...",
  "design_rationale": "2-3 sentences explaining the design direction",
  "color_palette": {
    "primary": "#hex",
    "primary_dark": "#hex",
    "primary_light": "#hex",
    "secondary": "#hex",
    "accent": "#hex",
    "background": "#hex",
    "surface": "#hex",
    "text_primary": "#hex",
    "text_secondary": "#hex",
    "text_on_primary": "#hex",
    "border": "#hex",
    "success": "#hex",
    "warning": "#hex"
  },
  "typography": {
    "font_heading": "font-family string",
    "font_body": "font-family string",
    "font_accent": "font-family string (for CTAs/badges)",
    "scale": {
      "h1": {"size": "rem", "weight": "number", "line_height": "number", "letter_spacing": "em or normal"},
      "h2": {"size": "rem", "weight": "number", "line_height": "number", "letter_spacing": "em or normal"},
      "h3": {"size": "rem", "weight": "number", "line_height": "number", "letter_spacing": "em or normal"},
      "h4": {"size": "rem", "weight": "number", "line_height": "number", "letter_spacing": "em or normal"},
      "body": {"size": "rem", "weight": "number", "line_height": "number"},
      "small": {"size": "rem", "weight": "number", "line_height": "number"},
      "caption": {"size": "rem", "weight": "number", "line_height": "number"}
    }
  },
  "spacing": {
    "section_padding_y": "rem",
    "section_padding_x": "rem",
    "container_max_width": "px",
    "grid_gap": "rem",
    "component_gap": "rem",
    "card_padding": "rem"
  },
  "sections": [
    {
      "name": "hero",
      "layout": "description of layout approach",
      "height": "vh or px",
      "background": "description (gradient, image style, overlay)",
      "image_style": "photography direction for stock/AI images",
      "overlay": "rgba value or gradient",
      "text_position": "left|center|right and vertical alignment",
      "text_max_width": "px or %",
      "cta_style": "description of button treatment"
    },
    {
      "name": "services",
      "layout": "grid|flex description with columns",
      "card_style": {
        "border_radius": "px",
        "shadow": "CSS box-shadow value",
        "padding": "rem",
        "hover_effect": "description",
        "icon_style": "description"
      }
    },
    {
      "name": "about/trust",
      "layout": "description",
      "elements": ["list of visual elements"]
    },
    {
      "name": "testimonials",
      "layout": "description",
      "card_style": {
        "border_radius": "px",
        "shadow": "CSS box-shadow value",
        "quote_style": "description"
      }
    },
    {
      "name": "cta_banner",
      "layout": "description",
      "background": "treatment",
      "button_style": "description"
    },
    {
      "name": "footer",
      "layout": "columns and arrangement",
      "background": "color/treatment"
    }
  ],
  "components": {
    "button_primary": {
      "padding": "rem values",
      "border_radius": "px",
      "font_weight": "number",
      "text_transform": "none|uppercase|capitalize",
      "shadow": "CSS value",
      "hover": "description of hover state"
    },
    "button_secondary": {
      "padding": "rem values",
      "border_radius": "px",
      "border": "CSS border value",
      "hover": "description"
    },
    "card": {
      "border_radius": "px",
      "shadow": "CSS box-shadow",
      "padding": "rem",
      "background": "color",
      "hover_shadow": "CSS box-shadow"
    },
    "input": {
      "border_radius": "px",
      "border": "CSS border",
      "padding": "rem values",
      "focus_ring": "CSS outline or box-shadow"
    },
    "badge": {
      "padding": "rem values",
      "border_radius": "px",
      "font_size": "rem",
      "font_weight": "number"
    }
  },
  "responsive": {
    "breakpoints": {
      "mobile": "px",
      "tablet": "px",
      "desktop": "px"
    },
    "mobile_adjustments": [
      "list of specific responsive behavior changes"
    ]
  }
}
"""

USER_PROMPT_TEMPLATE = """\
Create a complete page design specification for:

Business: {business_name}
Industry: {industry}
Creative Brief: {brief}

Produce the full JSON spec. Every value must be concrete (actual hex codes, actual \
rem values, actual CSS shadow strings) -- no placeholders or "TBD". The spec should \
be opinionated and cohesive, reflecting the brand personality described above.
"""


def generate_css_variables(spec: dict, client_id: str) -> str:
    """Convert a design spec JSON into a CSS custom properties file."""
    lines = [
        f"/* CSS Variables generated from design spec: {spec.get('business_name', client_id)} */",
        f"/* Industry: {spec.get('industry', 'unknown')} */",
        f"/* Generated: {time.strftime('%Y-%m-%d %H:%M:%S')} */",
        "",
        ":root {",
    ]

    # Colors
    lines.append("  /* Color Palette */")
    palette = spec.get("color_palette", {})
    for key, value in palette.items():
        css_var = f"  --color-{key.replace('_', '-')}: {value};"
        lines.append(css_var)

    # Typography
    lines.append("")
    lines.append("  /* Typography */")
    typo = spec.get("typography", {})
    if typo.get("font_heading"):
        lines.append(f"  --font-heading: {typo['font_heading']};")
    if typo.get("font_body"):
        lines.append(f"  --font-body: {typo['font_body']};")
    if typo.get("font_accent"):
        lines.append(f"  --font-accent: {typo['font_accent']};")

    scale = typo.get("scale", {})
    for level, props in scale.items():
        if isinstance(props, dict):
            if props.get("size"):
                lines.append(f"  --font-size-{level}: {props['size']};")
            if props.get("weight"):
                lines.append(f"  --font-weight-{level}: {props['weight']};")
            if props.get("line_height"):
                lines.append(f"  --line-height-{level}: {props['line_height']};")
            if props.get("letter_spacing") and props["letter_spacing"] != "normal":
                lines.append(f"  --letter-spacing-{level}: {props['letter_spacing']};")

    # Spacing
    lines.append("")
    lines.append("  /* Spacing */")
    spacing = spec.get("spacing", {})
    for key, value in spacing.items():
        css_var = f"  --spacing-{key.replace('_', '-')}: {value};"
        lines.append(css_var)

    # Components
    lines.append("")
    lines.append("  /* Components */")
    components = spec.get("components", {})

    # Card
    card = components.get("card", {})
    if card.get("border_radius"):
        lines.append(f"  --radius-card: {card['border_radius']};")
    if card.get("shadow"):
        lines.append(f"  --shadow-card: {card['shadow']};")
    if card.get("hover_shadow"):
        lines.append(f"  --shadow-card-hover: {card['hover_shadow']};")
    if card.get("padding"):
        lines.append(f"  --padding-card: {card['padding']};")

    # Buttons
    btn_primary = components.get("button_primary", {})
    if btn_primary.get("border_radius"):
        lines.append(f"  --radius-button: {btn_primary['border_radius']};")
    if btn_primary.get("padding"):
        lines.append(f"  --padding-button: {btn_primary['padding']};")
    if btn_primary.get("shadow"):
        lines.append(f"  --shadow-button: {btn_primary['shadow']};")

    # Input
    inp = components.get("input", {})
    if inp.get("border_radius"):
        lines.append(f"  --radius-input: {inp['border_radius']};")
    if inp.get("border"):
        lines.append(f"  --border-input: {inp['border']};")
    if inp.get("focus_ring"):
        lines.append(f"  --focus-ring-input: {inp['focus_ring']};")

    # Badge
    badge = components.get("badge", {})
    if badge.get("border_radius"):
        lines.append(f"  --radius-badge: {badge['border_radius']};")

    # Responsive breakpoints
    lines.append("")
    lines.append("  /* Breakpoints */")
    responsive = spec.get("responsive", {})
    breakpoints = responsive.get("breakpoints", {})
    for bp_name, bp_value in breakpoints.items():
        lines.append(f"  --breakpoint-{bp_name}: {bp_value};")

    lines.append("}")
    lines.append("")

    return "\n".join(lines)


def run_design_generation():
    """Run the design spec generation for all client briefs."""
    client = anthropic.Anthropic()  # uses ANTHROPIC_API_KEY from env

    results = []
    total_start = time.time()

    for brief_data in CLIENT_BRIEFS:
        client_id = brief_data["id"]
        print(f"\n{'='*60}")
        print(f"Generating spec for: {brief_data['business_name']} ({brief_data['industry']})")
        print(f"{'='*60}")

        user_prompt = USER_PROMPT_TEMPLATE.format(
            business_name=brief_data["business_name"],
            industry=brief_data["industry"],
            brief=brief_data["brief"],
        )

        start = time.time()

        # Attempt up to 2 tries (retry on JSON parse failure)
        spec = None
        for attempt in range(2):
            response = client.messages.create(
                model=MODEL,
                max_tokens=8192,
                system=SYSTEM_PROMPT,
                messages=[{"role": "user", "content": user_prompt}],
            )

            elapsed = time.time() - start
            raw_text = response.content[0].text

            # Parse JSON from response
            try:
                # Strip markdown fences if present
                import re
                cleaned = raw_text.strip()
                fence_match = re.search(r"```(?:json)?\s*([\s\S]*?)```", cleaned)
                if fence_match:
                    cleaned = fence_match.group(1).strip()
                else:
                    # Find outermost braces
                    first_brace = cleaned.find("{")
                    last_brace = cleaned.rfind("}")
                    if first_brace != -1 and last_brace != -1:
                        cleaned = cleaned[first_brace:last_brace + 1]

                spec = json.loads(cleaned)
                break  # Success
            except json.JSONDecodeError as e:
                if attempt == 0:
                    print(f"  -> JSON parse failed (attempt 1), retrying... Error: {e}")
                    start = time.time()  # Reset timer for retry
                else:
                    # Save raw response for debugging
                    debug_path = OUTPUT_DIR / f"{client_id}-raw-response.txt"
                    with open(debug_path, "w") as f:
                        f.write(raw_text)
                    print(f"  -> JSON parse failed on retry. Raw saved to {debug_path.name}")
                    raise

        assert spec is not None

        # Save JSON spec
        spec_path = OUTPUT_DIR / f"{client_id}-spec.json"
        with open(spec_path, "w") as f:
            json.dump(spec, f, indent=2)
        print(f"  -> Saved spec: {spec_path.name}")

        # Generate and save CSS variables
        css_content = generate_css_variables(spec, client_id)
        css_path = OUTPUT_DIR / f"{client_id}-variables.css"
        with open(css_path, "w") as f:
            f.write(css_content)
        print(f"  -> Saved CSS:  {css_path.name}")

        # Track results
        result_meta = {
            "client_id": client_id,
            "business_name": brief_data["business_name"],
            "industry": brief_data["industry"],
            "generation_time_seconds": round(elapsed, 2),
            "input_tokens": response.usage.input_tokens,
            "output_tokens": response.usage.output_tokens,
            "spec_keys": list(spec.keys()),
            "num_sections": len(spec.get("sections", [])),
            "num_colors": len(spec.get("color_palette", {})),
            "has_typography": bool(spec.get("typography")),
            "has_components": bool(spec.get("components")),
            "has_responsive": bool(spec.get("responsive")),
            "spec_file": str(spec_path),
            "css_file": str(css_path),
        }
        results.append(result_meta)

        print(f"  -> Time: {elapsed:.2f}s | Tokens: {response.usage.input_tokens}in / {response.usage.output_tokens}out")

    total_elapsed = time.time() - total_start

    # Save summary
    summary = {
        "test_run": time.strftime("%Y-%m-%d %H:%M:%S"),
        "model": MODEL,
        "total_time_seconds": round(total_elapsed, 2),
        "results": results,
    }
    summary_path = OUTPUT_DIR / "run-summary.json"
    with open(summary_path, "w") as f:
        json.dump(summary, f, indent=2)

    print(f"\n{'='*60}")
    print(f"COMPLETE - Total time: {total_elapsed:.2f}s")
    print(f"Summary saved: {summary_path}")
    print(f"{'='*60}\n")

    return summary


if __name__ == "__main__":
    run_design_generation()
