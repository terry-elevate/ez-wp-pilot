Tier 1 pilot is stood up locally (Docker: pilot site :8181, MainWP fleet dashboard :8282, MariaDB :33081) — all free tiers, $0 spent so far. Hands-on results:

**MainWP (fleet backbone)** ✅ — Dashboard + child connected and syncing. Key finding: the child connection is fully scriptable (`MainWP_Manage_Sites_View::add_wp_site()` via wp-cli), so onboarding EZ's 300 containers is an automation job, not 300 manual clicks.

**Zero-egress inference (the IT demo)** ✅ — WordPress → AI Engine → local Ollama (gemma4) round-trip verified; nothing leaves the machine. Caveat: free AI Engine lacks the native Ollama env type (Pro feature) — works fine via its "Custom (OpenAI-compatible)" env pointed at Ollama's `/v1` API.

**AI Engine MCP (production agent path)** ✅ — MCP server live with 43 curated WP tools behind a bearer token. Tested externally: an agent created a page + authored Gutenberg blocks via `wp_create_post` + `wp_write_blocks`. The tool emits guaranteed-valid core blocks; the model only supplies content.

**Location pages (the key pain point)** ✅ at small scale — Built a generator: 3 fixed Gutenberg layout templates, local AI fills content slots (JSON) with a distinct copy angle per city, layouts rotate. Generated 5 PA cities in one run: 0 invalid blocks, all pages render, **pairwise text similarity 11–33%** (spin-tax pages typically 70–90% — this is exactly the mass-production signature Google flags).

**The architecture finding worth keeping:** free-form block markup from small local models is ~90% right (renders fine, but shows "invalid block" in the editor — wrong inner HTML). Fixed templates + AI-filled content slots, or the MCP `wp_write_blocks` route, produce 100% valid blocks. Matches the "3–4 base templates" instinct from the AgentCore POC.

**Next (needs $ / signups):** Page Generator Pro (~$99), AI Engine Pro ($59), and the Tier 2 trials (Novamira isolated-container only; SEOmatic pending data-retention terms).
