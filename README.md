# EZ Solutions WordPress AI Pilot

Local pilot for the EZ Solutions engagement (Linear: ELVT-207 requirements, ELVT-208 market scan).
Tests AI-operated WordPress across fleet management, zero-egress inference, MCP agent access,
and the location-pages-at-scale workflow, against a fictional demo brand (**Keystone Comfort Co.**).

## Stack

`docker-compose.yml` — project `wp-pilot`:

| Service | URL / port | Notes |
|---|---|---|
| `wordpress` (pilot site) | http://localhost:8181 | 51 location pages, Keystone Comfort theme |
| `fleet` (MainWP dashboard) | http://localhost:8282 | pilot connected as child site |
| `db` (MariaDB 11) | localhost:33081 | databases `wordpress` + `fleet` |
| `wpcli` / `fleetcli` | `--profile cli` | run with `--user 33:33` (Alpine uid mismatch) |

Admin: `terry` / `elevate-Dev-8181` (pilot), `elevate-Dev-8282` (fleet). Dev creds only.

Plugins on pilot: MainWP Child, AI Engine (MCP at `POST /wp-json/mcp/v1/http`, bearer in
`mwai_options`), Yoast SEO, ACF, Page Generator, AI Services.

## Layout

- `theme/keystone/` — child theme of Twenty Twenty-Five: Fraunces/Inter (bundled), palette,
  header/footer parts, cover-hero page template, designed front page. Deployed to
  `wp-content/themes/keystone` via `docker cp`.
- `scripts/` — wp-cli `eval-file` scripts. Key ones:
  - `v3-assemble.php` — content JSON → designed Gutenberg pages (in-place updates by
    `_location_city` meta; featured-image heroes, imgcol sections, nearby links, Yoast meta)
  - `v3-gate.php` — hard quality gate per page: ≥1000 words, ≥2 images, valid blocks,
    unique title/opening/shingles vs corpus, meta description, no risky claims
  - `build-image-pool.php` — Openverse commercial-license image pool (`ez_img_pool` option)
  - `standards-check.php` / `diversity-audit.php` — corpus-wide QA
  - earlier generations (`multi-location-gen*.php`, `v2-from-json.php`) kept for the
    v1 → v2 → v3 comparison story
- `content/` — page content JSON consumed by the assemblers (v2 shallow, v3 deep,
  `v3-city-*.json` written by the agentic loop)
- `workflows/seo-location-page-loop.js` — the write → critique×3 → revise → gate loop
  (Claude Code Workflow) that produces the 45 remaining deep pages
- `mu-plugins/activity-cpt.php` — Events/Programs CPT for the ACF acceleration demo

## Lessons encoded here

1. Small local models write ~90% valid Gutenberg; fixed templates or MCP `wp_write_blocks`
   get to 100%. Structure from tooling, words from models.
2. Machine dedupe (shingle overlap) and human sameness are different bars — gate both
   (see `v3-gate.php`: shingle ≤0.08 AND unique title patterns/opening frames).
3. Depth is a gate, not a hope: EZ's real standard is 400–1000+ words with imagery;
   anything less fails loudly now.
4. Content carries no invented business claims; the demo brand is labeled fictional.
