# AI Prompt System for Website Building + Content Generation

Distilled from a real 8-day pilot: 51 published WordPress location pages, 13 design
concepts, a quality-gate pipeline, and an image-model benchmark — all driven through
prompts. This is the system that emerged, the prompts cleaned up (profanity removed,
typos fixed), and the moves that actually changed outcomes.

---

## The workflow that made the difference

Six phases, in order. Skipping a phase is what produced every bad round.

### 1. Environment + constraints first
Give the agent the whole operating envelope in one message — stack, budget, and
what each tool is supposed to prove.

> "Set up a WordPress container on an uncommon port + DB port. Tier 1 — core pilot:
> MainWP (fleet backbone test), Page Generator Pro (50+ location pages), WP AI Client +
> Ollama (zero-egress inference), AI Engine Pro (curated MCP)… so we can use AI to
> heavily customize WordPress content + theme + plugins."

Then get out of the way: *"just work on it, I'm busy"*, *"keep going"*, *"go"*.
Short momentum nudges beat re-explaining.

### 2. Design system before any page
The single biggest lesson. Pages generated without a design system converge on the
same AI-flavored sameness, no matter how good the content is.

- **Design comps first, in a real design tool** (Pencil), one visual identity per direction.
- **Export the comp to HTML and extract exact tokens.** "Never write CSS from a
  screenshot" — `#4A7FBF`, not "a blue"; `16px/600`, not "bold-ish".
- **Feed the agent the real brand standards**, not vibes:
  > "This is their copy guidelines that we should keep and research — build an agent
  > that follows it." (attach the actual checklist doc)
- **Anchor taste externally** when output is generic:
  > "Find layout libraries / design tastes online." · "Think Squarespace templates."

### 3. Score everything with numbers, brutally
Vague praise requests produce vague output. Numeric grades force real iteration.

> "Create a line-by-line grid: requirement, solution, result, quality — and iterate
> until quality is all 90%." · "Iterate to 95%."
> "Quality is quality review — not just 'works, shows up, exists'."
> "I gave it 0/100." · "Great, you went from 0/100 to 2/100."

And make the agent keep its own score so nothing gets dropped:

> "To make sure the docs are followed, the agent loop should keep track of a list of
> items and score each 0–10, so no item gets left behind."

That became a 14-criteria self-scored checklist the agent ran every generation round.

### 4. Screenshot-driven visual QA
Text descriptions of visual bugs get misinterpreted. A screenshot plus one pointed
sentence never did.

> [screenshot] "Double hero header."
> [screenshot] "Why is this all empty?"
> [screenshot] "The title + subtitle are so far away."
> [screenshot] "Fix this contact page — the forms are overlapping."

When the agent claims something is fixed, demand it look for itself: *"show me the
page"* — headless-browser screenshots by the agent caught three bugs its own CSS
reasoning had missed.

**Build a visual test harness**: one page rendering every component in every
background context. Its first run caught three contrast bugs on the spot.

### 5. Automated gates on every render
Human review doesn't scale to 51 pages. Encode the floor:

- ≥1,000 words, ≥2 images, paragraph length caps, metadata present
- Cross-page uniqueness (shingle overlap ≤ 0.08) — the anti-sameness metric
- Block validity, no unsupported business claims
- Run the gate **after every render**, not as a final pass

### 6. Edit the output like an editor, not a fan
Paste the generated copy back and mark it up directly:

> "These are too verbose." · "Stay high level, at business level."
> "Learnings are not about failures and mistakes — just facts of what was tried and
> what happened." · "Be direct, down to earth, authentic."
> "Don't need these notes." · "I don't want this question header."
> "Kill this header." (paste the exact text to remove)

Naming what you *don't* want is as productive as describing what you do.

---

## Concrete tips (each one traceable to a real save)

1. **Grade 0–100 and mean it.** "0/100" triggered a rebuild; "looks okay" would have
   shipped sameness.
2. **One screenshot > three paragraphs.** Every layout bug was fixed fastest when
   reported as image + one sentence.
3. **Comp → HTML export → tokens → implementation.** The only reliable path from
   design intent to CSS.
4. **Give real source documents.** The copy checklist and design standards docs beat
   any prompt-described style guide.
5. **Force diversity explicitly.** "I am not convinced the content is diverse
   enough" / "there is no diversity at all" — then verify with a computed similarity
   metric, not eyeballing.
6. **Make the agent self-score against a checklist** (0–10 per item) so long
   requirement lists survive across iterations.
7. **Ask "where is X?" often.** ("Where is our findings grid?" "Where was the design
   guide?") Artifacts silently drop during big rewrites; inventory questions catch it.
8. **Batch the close-out**: "Clear up all pending items and do it now so we can
   conclude" — turned a wishlist into shipped work in one pass.
9. **Label provenance**: "Label AI image + stock image" — one prompt, permanent
   trust feature.
10. **Benchmark spend before scaling**: "Try all [image models]… build a matrix of
    cost + quality" — found mid-tier price convergence (~$0.04) and a 10× speed spread.
11. **Publish early to a real URL** — half the layout bugs only surfaced when viewing
    the deployed static copy.
12. **Package with no secrets**: "Must not have any secrets" — scan dumps for keys
    before zipping; redact with same-length placeholders so serialized data survives.

---

## Reusable prompt templates

**Project kickoff**
> Set up [stack] on [constraints]. Here's the tool tier list and what each must prove:
> [tool → hypothesis]. Goal: [outcome]. Work autonomously; check in with previews.

**Design-system-first**
> Before generating any page: create [N] design comps in [tool], one distinct visual
> identity each, grounded in [brand standards doc]. Export each to HTML and extract
> exact tokens (hex, px, weights). Implementation must use only extracted tokens.

**Quality grid**
> Create a line-by-line grid: requirement · solution · result · quality (0–100).
> Quality means reviewed quality, not "exists". Iterate until every row ≥ [bar].

**Self-scoring loop**
> Keep a running checklist of [criteria]. After each generation, score every item
> 0–10 and show the scorecard. Do not consider a round done below [threshold].

**Anti-sameness**
> Generate [N] pages that pass this test: reviewed side by side, no two share layout
> rhythm, opening pattern, or hero treatment. Verify with [uniqueness metric ≤ X].

**Visual QA**
> [screenshot] + one sentence naming the defect. Then: "Screenshot the page yourself
> after the fix and confirm."

**Editorial pass**
> [paste generated copy] Too verbose / stay at business level / remove X / be direct,
> down to earth, authentic. Facts of what was tried and what happened — not narrative.

**Ship it**
> Clear up all pending items now so we can conclude. Then package [code, DB, exports]
> into a zip with a restore README. Must contain no secrets.

---

## Appendix: the cleaned prompt log (highlights)

Chronological, profanity removed, typos fixed. The short ones are real — brevity
plus a screenshot was the dominant working style.

- "Set up a WordPress container on an uncommon port + DB port… [tool tier list]"
- "Just work on it, I'm busy" / "keep going" / "go"
- "Show me a preview" · "Make sure every requirement is handled"
- "I am not convinced the content you created is diverse enough"
- "Confirmed — it's all bad. You have to fix it. There was no design. No taste. No effort or thoughtfulness."
- "Still super short files/pages?"
- "Create a line-by-line grid: requirement, solution, result, quality" · "Iterate until quality is all 90%" · "Iterate to 95%"
- "Quality is quality review, not just 'works / shows up / exists'"
- "There is no diversity at all. I gave it 0/100." · "Great — you went from 0/100 to 2/100."
- "Find layout libraries / design tastes online" · "Think Squarespace templates"
- "The blocks are still too similar to each other" · "Only 12?"
- "This is their copy guidelines — keep it, research it, and build an agent that follows it"
- "The agent loop should keep a list of items and score them 0–10 so nothing gets left behind"
- "Do more designs — where are the screenshots of those website examples?"
- "Iterate: the new ones aren't as nuanced as the first one"
- "Better, but I don't see you applying the designs to the pages at all"
- "Audit and rate the page against our scoring guide"
- [screenshot] "Double hero header" · [screenshot] "Why is this all empty?" · [screenshot] "Bad scaling" · [screenshot] "Wrong layout"
- "Summarize all the things we tried — and learnings — build an HTML page that walks through each item. Show the results vividly."
- "This design is bad — upgrade" · "Fix the content: the design guideline isn't matching our Pencil design"
- "Show all the designs" · "All the pages we built — with pagination"
- "The hero layout makes the screenshot too long" · "We should have different heros and different menu options"
- "Label AI image + stock image" · "Can we play with different models? Build a matrix of cost + quality"
- "Clear up all the pending items and do it now so we can conclude"
- "Can we screenshot all the webpages so the learnings HTML works as a static site?" · "Deploy WordPress to somewhere" · "Create a repo and a deployment for me"
- "Kill this header… don't call them 'customer'. Just say the requirements from meetings. Be direct, down to earth, authentic."
- "These are too verbose" · "Stay high level at business level" · "Don't need these notes"
- [screenshot] "Fix this contact page — the forms are overlapping"
- "Package the WordPress changes, DB, and the learning files into a zip and README" · "Must not have any secrets"
