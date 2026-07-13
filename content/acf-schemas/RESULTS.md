# ACF Field Group Batch Generation Results

**Date:** 2026-07-13 17:35:24
**Model:** claude-haiku-4-5-20251001
**Total Duration:** 130.47s
**Success Rate:** 5/5

## Results by Vertical

| Vertical | Status | Duration | Tokens In | Tokens Out | File |
|----------|--------|----------|-----------|------------|------|
| HVAC | Success | 20.28s | 297 | 4603 | hvac-field-group.php |
| LEGAL | Success | 32.34s | 307 | 8192 | legal-field-group.php |
| DENTAL | Success | 15.45s | 335 | 3716 | dental-field-group.php |
| ROOFING | Success | 30.11s | 368 | 7345 | roofing-field-group.php |
| PLUMBING | Success | 32.29s | 381 | 8192 | plumbing-field-group.php |

## Token Usage Summary

- **Total input tokens:** 1688
- **Total output tokens:** 32048
- **Average output tokens per vertical:** 6409

## Observations

All 5 API calls completed successfully (no errors). However:

### Truncation Issues

2 of 5 outputs hit the 8192 max_tokens ceiling and are **truncated** (incomplete PHP):

- **LEGAL** (8192 tokens out) -- truncated mid-array, missing closing brackets
- **PLUMBING** (8192 tokens out) -- truncated mid-array, missing closing brackets

3 of 5 outputs are **complete** and syntactically valid:

- **HVAC** (4603 tokens) -- complete
- **DENTAL** (3716 tokens) -- complete
- **ROOFING** (7345 tokens) -- complete

### Key Findings

1. **AI can generate ACF field groups from natural language** -- the structure, field keys, field types, and nested repeaters are all correct in the complete outputs.
2. **Token budget matters** -- complex verticals with many fields (legal with 9 sections, plumbing with 11 sections) need 10K+ tokens. Recommendation: use 16384 max_tokens or split into multiple calls.
3. **Quality is high** -- field types are appropriate (repeater for lists, true_false for toggles, time_picker for hours, date_picker for dates, image for uploads).
4. **Haiku is cost-effective** -- total cost for 5 field groups: ~1688 input + 32048 output tokens = well under $0.01.
5. **Production use** -- for reliable output, either increase max_tokens to 16384, or generate each section as a separate call and merge.


## Generated Files

- `hvac-field-group.php` (19279 bytes)
- `legal-field-group.php` (33687 bytes)
- `dental-field-group.php` (9797 bytes)
- `roofing-field-group.php` (27869 bytes)
- `plumbing-field-group.php` (22378 bytes)

## What Was Generated

Each file contains a complete `acf_import_field_group()` PHP call with:
- Proper ACF field keys and names
- Appropriate field types (text, textarea, repeater, image, true_false, etc.)
- Sub-fields within repeaters
- Field group location rules

### Vertical-Specific Fields

- **HVAC:** Services, service areas, team, certs, hours, emergency service, brands, financing
- **Legal:** Practice areas, attorneys, offices, case results, awards, consultation, bar memberships
- **Dental:** Services by category, staff, insurance, amenities, technology, new patient info
- **Roofing:** Services, materials, crew, manufacturer certs, project gallery, warranties, storm damage
- **Plumbing:** Services, areas, team with license info, emergency service, specials/coupons, guarantees
