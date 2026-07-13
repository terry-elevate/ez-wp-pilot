#!/usr/bin/env python3
"""
ACF Field Group Batch Generator
Generates ACF field groups from natural language descriptions using Claude Haiku.
Tests whether AI can produce production-quality acf_import_field_group() PHP arrays
for different business verticals that EZ Solutions works with.
"""

import os
import sys
import time
from pathlib import Path

import anthropic

# Configuration
MODEL = "claude-haiku-4-5-20251001"
OUTPUT_DIR = Path("/Users/txu/Repo/wordpress/content/acf-schemas")
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

# Business verticals and their field group descriptions
VERTICALS = {
    "hvac": {
        "filename": "hvac-field-group.php",
        "description": """Generate a complete ACF (Advanced Custom Fields) field group for an HVAC company website.
The field group should be registered via acf_import_field_group() and include fields for:
- Company services (repeater: service name, description, icon class, price range)
- Service areas (repeater: city/town name, zip codes served, radius miles)
- Team members (repeater: name, role/title, photo, certifications list, years experience, bio)
- Certifications & licenses (repeater: cert name, issuing body, license number, expiration date)
- Business hours (repeater for each day: day name, open time, close time, is_closed toggle)
- Emergency service availability (true/false, after-hours phone number, response time guarantee)
- Brands serviced (repeater: brand name, brand logo, authorized dealer toggle)
- Financing options (textarea for description, repeater: plan name, term months, APR)

Use field group key 'group_hvac_company_fields'. Use proper ACF field types (text, textarea, image, repeater, true_false, date_picker, time_picker, number, select).
Output ONLY the complete PHP code with the acf_import_field_group() call, no explanation."""
    },
    "legal": {
        "filename": "legal-field-group.php",
        "description": """Generate a complete ACF (Advanced Custom Fields) field group for a law firm website.
The field group should be registered via acf_import_field_group() and include fields for:
- Practice areas (repeater: area name, description, icon, is_primary toggle)
- Attorney profiles (repeater: name, title/position, photo, bar admissions repeater, education repeater, practice focus areas, bio, email, phone)
- Office locations (repeater: office name, address, city, state, zip, phone, fax, map coordinates, parking info)
- Case results / testimonials (repeater: case type, result summary, client testimonial, is_anonymous toggle, date)
- Awards & recognitions (repeater: award name, issuing organization, year, badge image)
- Business hours (repeater for each day: day name, open time, close time)
- Free consultation settings (toggle, consultation types: phone/in-person/video, booking URL)
- Bar associations & memberships (repeater: org name, member since year, member ID)
- Languages spoken (repeater: language name, fluency level)

Use field group key 'group_legal_firm_fields'. Use proper ACF field types.
Output ONLY the complete PHP code with the acf_import_field_group() call, no explanation."""
    },
    "dental": {
        "filename": "dental-field-group.php",
        "description": """Generate a complete ACF (Advanced Custom Fields) field group for a dental practice website.
The field group should be registered via acf_import_field_group() and include fields for:
- Services offered (repeater: service name, category [general/cosmetic/orthodontic/surgical/pediatric], description, typical duration, price range, before/after images)
- Dentist & staff profiles (repeater: name, title, photo, credentials/degrees, specializations, years experience, bio, fun fact)
- Insurance accepted (repeater: provider name, plan types accepted, network status [in-network/out-of-network])
- Patient amenities (repeater: amenity name, description, icon)
- Technology & equipment (repeater: technology name, description, benefit to patient, image)
- Office hours (repeater for each day: day name, open time, close time, is_closed toggle)
- Emergency dental info (toggle for same-day appointments, emergency phone, after-hours instructions textarea)
- New patient info (welcome message textarea, forms download URL, what to bring repeater)
- Service areas (repeater: city name, distance from office, accepts_patients toggle)
- Payment options (repeater: option name, description, financing available toggle)

Use field group key 'group_dental_practice_fields'. Use proper ACF field types.
Output ONLY the complete PHP code with the acf_import_field_group() call, no explanation."""
    },
    "roofing": {
        "filename": "roofing-field-group.php",
        "description": """Generate a complete ACF (Advanced Custom Fields) field group for a roofing contractor website.
The field group should be registered via acf_import_field_group() and include fields for:
- Services (repeater: service name [roof repair/replacement/inspection/gutter/siding/etc], description, residential_or_commercial select, image)
- Roofing materials (repeater: material name [asphalt shingles/metal/tile/slate/flat/TPO], description, warranty years, pros, cons, image)
- Service areas (repeater: city/county name, zip codes, travel fee if applicable)
- Team / crew info (repeater: name, role, photo, certifications, years experience)
- Certifications & manufacturer partnerships (repeater: cert name, manufacturer, cert level [preferred/elite/master], cert image, cert number)
- Project gallery (repeater: project title, before photo, after photo, roof type, description, city)
- Warranty information (repeater: warranty type, coverage description, duration years, terms)
- Insurance & bonding (license number, insurance carrier, bond amount, insurance cert upload)
- Storm damage / emergency service (toggle, response time, storm types handled repeater, insurance claim assistance toggle)
- Financing (repeater: lender name, terms, minimum credit score, apply URL)
- Business hours and seasonal availability notes

Use field group key 'group_roofing_contractor_fields'. Use proper ACF field types.
Output ONLY the complete PHP code with the acf_import_field_group() call, no explanation."""
    },
    "plumbing": {
        "filename": "plumbing-field-group.php",
        "description": """Generate a complete ACF (Advanced Custom Fields) field group for a plumbing company website.
The field group should be registered via acf_import_field_group() and include fields for:
- Services (repeater: service name, category [residential/commercial/emergency], description, typical price range, estimated duration, image)
- Service areas (repeater: city/town, zip codes served, same_day_available toggle)
- Team members (repeater: name, role [master plumber/journeyman/apprentice/office], photo, license number, certifications, years experience, bio)
- Licenses & certifications (repeater: license type, number, state, expiration date, issuing authority)
- Business hours (repeater for each day: day, open time, close time, is_closed toggle)
- Emergency / 24-7 service (toggle, emergency phone, average response time, dispatch fee, areas covered for emergency)
- Brands & products (repeater: brand name, logo, product categories, preferred_vendor toggle)
- Specials & coupons (repeater: offer title, description, discount amount, code, valid_from date, valid_until date, terms)
- Service guarantees (repeater: guarantee name, description, duration)
- Payment methods accepted (checkbox group: cash, check, credit card types, financing)
- Customer satisfaction metrics (number fields: years in business, jobs completed, satisfaction percentage, google review average)

Use field group key 'group_plumbing_company_fields'. Use proper ACF field types.
Output ONLY the complete PHP code with the acf_import_field_group() call, no explanation."""
    },
}


def generate_acf_field_group(client: anthropic.Anthropic, vertical: str, config: dict) -> dict:
    """Generate an ACF field group for a given business vertical."""
    print(f"\n{'='*60}")
    print(f"Generating ACF field group for: {vertical.upper()}")
    print(f"{'='*60}")

    start_time = time.time()
    result = {
        "vertical": vertical,
        "filename": config["filename"],
        "success": False,
        "error": None,
        "tokens_in": 0,
        "tokens_out": 0,
        "duration_sec": 0,
        "output_path": None,
    }

    try:
        response = client.messages.create(
            model=MODEL,
            max_tokens=8192,
            messages=[
                {
                    "role": "user",
                    "content": config["description"],
                }
            ],
        )

        duration = time.time() - start_time
        result["duration_sec"] = round(duration, 2)
        result["tokens_in"] = response.usage.input_tokens
        result["tokens_out"] = response.usage.output_tokens

        # Extract the text content
        content = response.content[0].text

        # Clean up: remove markdown code fences if present
        if content.startswith("```php"):
            content = content[6:]
        elif content.startswith("```"):
            content = content[3:]
        if content.endswith("```"):
            content = content[:-3]
        content = content.strip()

        # Ensure it starts with <?php
        if not content.startswith("<?php"):
            content = "<?php\n" + content

        # Save to file
        output_path = OUTPUT_DIR / config["filename"]
        output_path.write_text(content, encoding="utf-8")
        result["output_path"] = str(output_path)
        result["success"] = True

        print(f"  Duration: {duration:.2f}s")
        print(f"  Tokens: {result['tokens_in']} in / {result['tokens_out']} out")
        print(f"  Saved to: {output_path}")
        print(f"  File size: {output_path.stat().st_size} bytes")

    except Exception as e:
        duration = time.time() - start_time
        result["duration_sec"] = round(duration, 2)
        result["error"] = str(e)
        print(f"  ERROR: {e}")

    return result


def main():
    # Verify API key is set
    if not os.environ.get("ANTHROPIC_API_KEY"):
        print("ERROR: ANTHROPIC_API_KEY environment variable not set.")
        sys.exit(1)

    client = anthropic.Anthropic()

    print("ACF Field Group Batch Generator")
    print(f"Model: {MODEL}")
    print(f"Output directory: {OUTPUT_DIR}")
    print(f"Verticals: {', '.join(VERTICALS.keys())}")

    results = []
    total_start = time.time()

    for vertical, config in VERTICALS.items():
        result = generate_acf_field_group(client, vertical, config)
        results.append(result)

    total_duration = time.time() - total_start

    # Print summary
    print(f"\n{'='*60}")
    print("BATCH GENERATION SUMMARY")
    print(f"{'='*60}")
    print(f"Total duration: {total_duration:.2f}s")
    print(f"Successful: {sum(1 for r in results if r['success'])}/{len(results)}")
    print(f"Total tokens in:  {sum(r['tokens_in'] for r in results)}")
    print(f"Total tokens out: {sum(r['tokens_out'] for r in results)}")

    for r in results:
        status = "OK" if r["success"] else f"FAIL: {r['error']}"
        print(f"  {r['vertical']:10s} - {status} ({r['duration_sec']}s, {r['tokens_out']} tokens out)")

    # Write results summary as markdown
    summary_path = OUTPUT_DIR / "RESULTS.md"
    with open(summary_path, "w") as f:
        f.write("# ACF Field Group Batch Generation Results\n\n")
        f.write(f"**Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write(f"**Model:** {MODEL}\n")
        f.write(f"**Total Duration:** {total_duration:.2f}s\n")
        f.write(f"**Success Rate:** {sum(1 for r in results if r['success'])}/{len(results)}\n\n")
        f.write("## Results by Vertical\n\n")
        f.write("| Vertical | Status | Duration | Tokens In | Tokens Out | File |\n")
        f.write("|----------|--------|----------|-----------|------------|------|\n")
        for r in results:
            status = "Success" if r["success"] else f"Failed: {r['error']}"
            filename = r["filename"] if r["success"] else "N/A"
            f.write(f"| {r['vertical'].upper()} | {status} | {r['duration_sec']}s | {r['tokens_in']} | {r['tokens_out']} | {filename} |\n")

        f.write("\n## Token Usage Summary\n\n")
        f.write(f"- **Total input tokens:** {sum(r['tokens_in'] for r in results)}\n")
        f.write(f"- **Total output tokens:** {sum(r['tokens_out'] for r in results)}\n")
        f.write(f"- **Average output tokens per vertical:** {sum(r['tokens_out'] for r in results) // max(len(results), 1)}\n")

        f.write("\n## Observations\n\n")
        failures = [r for r in results if not r["success"]]
        if failures:
            f.write("### Issues Encountered\n\n")
            for r in failures:
                f.write(f"- **{r['vertical'].upper()}:** {r['error']}\n")
        else:
            f.write("All 5 field groups generated successfully with no errors.\n\n")

        f.write("\n## Generated Files\n\n")
        for r in results:
            if r["success"]:
                fpath = Path(r["output_path"])
                size = fpath.stat().st_size
                f.write(f"- `{r['filename']}` ({size} bytes)\n")

        f.write("\n## What Was Generated\n\n")
        f.write("Each file contains a complete `acf_import_field_group()` PHP call with:\n")
        f.write("- Proper ACF field keys and names\n")
        f.write("- Appropriate field types (text, textarea, repeater, image, true_false, etc.)\n")
        f.write("- Sub-fields within repeaters\n")
        f.write("- Field group location rules\n\n")
        f.write("### Vertical-Specific Fields\n\n")
        f.write("- **HVAC:** Services, service areas, team, certs, hours, emergency service, brands, financing\n")
        f.write("- **Legal:** Practice areas, attorneys, offices, case results, awards, consultation, bar memberships\n")
        f.write("- **Dental:** Services by category, staff, insurance, amenities, technology, new patient info\n")
        f.write("- **Roofing:** Services, materials, crew, manufacturer certs, project gallery, warranties, storm damage\n")
        f.write("- **Plumbing:** Services, areas, team with license info, emergency service, specials/coupons, guarantees\n")

    print(f"\nResults summary saved to: {summary_path}")
    return 0 if all(r["success"] for r in results) else 1


if __name__ == "__main__":
    sys.exit(main())
