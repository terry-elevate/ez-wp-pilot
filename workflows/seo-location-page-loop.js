export const meta = {
  name: 'seo-location-page-loop',
  description: 'Write→critique→revise→gate loop producing 45 deep, unique, SEO-optimized location pages',
  phases: [
    { title: 'Seed', detail: 'digest of accepted pages + banned patterns' },
    { title: 'Waves', detail: '9 waves × 5 cities: write, critique×3, revise, publish+gate' },
  ],
}

const SCRATCH = '/private/tmp/claude-501/-Users-txu-Repo-wordpress/0fcca77e-d160-42d4-92b6-7111375b1adf/scratchpad'
const REPO = '/Users/txu/Repo/wordpress'
const RUN = `cd ${REPO} && docker compose run --user 33:33 -v ${SCRATCH}:/scripts --rm wpcli eval-file`

const IMAGE_TOPICS = 'hero_house, hero_street, hero_winter, furnace, radiator, thermostat, minisplit, condenser, ductwork, boiler, technician, attic, basement, woodstove, victorian, rowhouse, workshop, filter'

const CITIES = [
  ['Bethlehem, PA','ventilation, ERVs and filtration for historic-district houses that were tightened for efficiency'],
  ['Erie, PA','lake-effect winters: runtime-based furnace aging, intake icing, outage readiness'],
  ['Pittsburgh, PA','ductless design for vertical rowhouses on slopes; zoning by floor; line-set routing on brick'],
  ['Altoona, PA','the pre-winter tune-up: what a measured combustion-analysis visit actually covers and catches'],
  ['State College, PA','rental-property HVAC management: tenant wear, thermostat limits, semester-aware scheduling, documentation'],
  ['Williamsport, PA','boiler care for grand Victorians: balancing century-old retrofits, outdoor-reset controls'],
  ['Wilkes-Barre, PA','valley-cold winter readiness: phone triage, van stock logic, what fails first in deep cold'],
  ['Easton, PA','repair-versus-replace math for aging air conditioners: R-22 economics, efficiency deltas, install day'],
  ['Pottstown, PA','efficiency upgrades that do not require new equipment: duct sealing, ECM blowers, schedules, rate plans'],
  ['Norristown, PA','smart thermostats done right: matching stat to system wiring, setup that actually saves'],
  ['King of Prussia, PA','two-story townhomes that run hot upstairs: zone dampers versus a dedicated upstairs head'],
  ['West Chester, PA','allergy-grade air: media filtration, UV at the coil, humidity targets, filtered fresh-air intake'],
  ['Media, PA','quiet ductless living for twins and semis: sound ratings, compact condensers, winter heating duty'],
  ['Doylestown, PA','replacing HVAC inside historic borough homes: load math for stone walls, duct reuse testing, permits'],
  ['Quakertown, PA','retiring the oil tank: gas-line path versus cold-climate heat pump path, remediation, rebates'],
  ['Scranton, PA','one-pipe steam heat mastery: venting, pitch, water quality, why rip-it-out advice is usually wrong'],
  ['Chambersburg, PA','what a maintenance plan actually buys: measured visits, trend lines, priority dispatch, honest math'],
  ['Mechanicsburg, PA','the two duct shortcuts in 1990s-2000s builds: missing returns and garden-hose flex runs'],
  ['Hershey, PA','family air quality for busy households: filter cabinets, pets, portable-purifier myths'],
  ['Lebanon, PA','heating on a fixed budget: spending order that lets eventual equipment be smaller and cheaper'],
  ['Lewisburg, PA','innkeeper HVAC: room-by-room control, decibel-first equipment selection, shoulder-season agility'],
  ['Selinsgrove, PA','life after the oil furnace: propane hybrid versus full electrification for river-valley homes'],
  ['Hazleton, PA','heating at 1,700 feet: mountain design temperatures, wind infiltration, two-stage equipment, vent snow clearance'],
  ['Stroudsburg, PA','freeze protection for second homes: monitored setpoints, cellular backup sensors, winterize-versus-heat math'],
  ['East Stroudsburg, PA','finished basements and additions: load-testing before drywall, dedicated heads for new spaces'],
  ['Phoenixville, PA','conditioning converted lofts: destratification, high returns, spot cooling by exposure zone'],
  ['Coatesville, PA','first-time homeowner orientation: know your filter, shutoffs, warning smells, first-year service'],
  ['Ephrata, PA','the warning signs furnaces give before failing: short cycling, flame color, rhythmic noises, CO whispers'],
  ['Lititz, PA','apartments above shops: slim equipment for tight quarters, separating floors and bills'],
  ['Columbia, PA','mixed-use buildings: untangling inherited ductwork, rooftop units downstairs with ductless up'],
  ['Gettysburg, PA','discreet installs inside a historic district: sight-line placement, high-velocity retrofits, review-board packets'],
  ['Hanover, PA','HVAC folk wisdom fact-checked: closed vents, oversizing, thermostat cranking, filter myths'],
  ['Shippensburg, PA','duplex turnover routine: the vacancy-window maintenance list that prevents mid-lease failures'],
  ['Waynesboro, PA','heating workshops, garages and pole barns: unit heaters versus radiant tube versus mini-splits'],
  ['Greensburg, PA','aging in place: temperature stability as a safety system, remote family visibility, filtration for older lungs'],
  ['Johnstown, PA','mechanicals in a flood city: platform elevation, first-floor relocation, what survives a wet basement'],
  ['Indiana, PA','farmhouse draft-proofing: band joists, balloon-frame chases, storm windows, duct runs through cold space'],
  ['Butler, PA','the spring AC startup sequence: homeowner half and instrument half, what each catches'],
  ['Beaver Falls, PA','two-family houses: splitting one system into zones versus full per-unit separation and metering'],
  ['New Castle, PA','fixer-upper HVAC triage: pre-offer assessments, renovation sequencing that avoids paying twice'],
  ['Meadville, PA','damp springs and mold prevention: hygrometer targets, crawlspace strategy, downspouts before dehumidifiers'],
  ['Oil City, PA','heritage homes with original windows: balancing before replacing, interior storms, precise supplemental heat'],
  ['Warren, PA','pairing wood stoves with modern systems: distributing stove heat, heat pump as the automatic partner'],
  ['Bradford, PA','engineering for the coldest corner of the state: design temps, dual fuel crossover, frozen condensate'],
  ['DuBois, PA','seasonal camps and lake houses: opening and closing rituals, wildlife inspections, storm-check routes'],
]

const CLAIMS_POLICY = `NEVER write: response-time promises, "24/7" anything, staffing claims, specific prices, "licensed and insured", "free estimate", "guaranteed" anything, or invented customer counts/years-in-business. The brand (Keystone Comfort Co.) is a demo; every claim must be one any competent HVAC company could honor. CTAs sell a paid measurement/assessment visit.`

const SCHEMA_NOTE = `The JSON file must be an ARRAY with ONE object matching the schema of ${SCRATCH}/v3-content-carlisle.json (Read it first — it is the canonical example). Required keys: city, hero_topic, hook, intro (array of 2 paragraphs), sections (array of 4-6, each {title, layout: "text"|"imgcol"|"textlist", paras: [...], image_topic+img_side for imgcol, list for textlist}), local {title, paras}, faq_heading, faq (3 of {q,a}), closing {title, para, cta}. ALSO add two NEW keys: meta_description (compelling, <=155 chars, includes city) and nearby (array of 2-3 other city strings chosen from the service list, exact format "Town, PA"). EXACTLY 2 sections must be layout "imgcol" (one img_side left, one right) with image_topic chosen from: ${IMAGE_TOPICS}. Straight apostrophes only. Valid JSON — no trailing commas, no markdown fences.`

phase('Seed')
const digestSeed = await agent(
  `Read ${SCRATCH}/v3-content-carlisle.json and ${SCRATCH}/v3-content-b1.json. Produce a compact "corpus digest": one line per page — city | masked title pattern | first 6 words of hook | section topics | CTA text. Then list every opening frame (first 3 words of each hook and each intro paragraph) and 15 distinctive phrases from these pages that future pages must NOT reuse. Return only the digest text.`,
  { label: 'seed-digest', phase: 'Seed' }
)

let digest = digestSeed || ''
const passed = [], failed = []
const chunk = (a, n) => Array.from({ length: Math.ceil(a.length / n) }, (_, i) => a.slice(i * n, i * n + n))
const waves = chunk(CITIES.map((c, i) => ({ city: c[0], angle: c[1], idx: i })), 5)

for (let w = 0; w < waves.length; w++) {
  const wavePhase = 'Waves'
  const results = await parallel(waves[w].map(({ city, angle, idx }) => async () => {
    const file = `${SCRATCH}/v3-city-${idx}.json`

    // 1. writer
    await agent(
      `You are the senior copywriter for a Pennsylvania HVAC brand's location pages. Write the complete page for ${city}. Editorial angle (the page is ABOUT this, not generic HVAC): ${angle}.\n\n${SCHEMA_NOTE}\n\nDepth: 1,250-1,700 words total across all text fields — substantive, specific, technically accurate HVAC content a homeowner would bookmark. Use real, verifiable local geography only (county names, rivers, well-known landmarks); when unsure, stay general rather than invent. ${CLAIMS_POLICY}\n\nUNIQUENESS — the corpus digest below lists what already exists. Your title pattern, hook opening, intro opening, section topics, phrasing, and CTA must all be clearly distinct from every line of it:\n${digest}\n\nWrite the file to ${file} (overwrite if present). Return only: your title, hook first 6 words, section topic list, CTA.`,
      { label: `write:${city}`, phase: wavePhase }
    )

    // 2. three critics in parallel, distinct lenses
    const critiques = await parallel([
      () => agent(`Read ${file}. You are an SEO editor reviewing a local landing page for ${city} (angle: ${angle}). Judge: search-intent coverage for someone searching this topic + city; heading quality (do h2s contain natural query language?); meta_description quality; content depth per section (flag any section under 80 words); internal-link choices in "nearby". Return a numbered list of concrete fixes, most important first, max 6. If publishable as-is, return "OK".`, { label: `seo:${city}`, phase: wavePhase }),
      () => agent(`Read ${file}. You are a uniqueness auditor. Corpus digest of existing pages:\n${digest}\n\nFlag: any title pattern, opening frame, section topic, phrase, or CTA resembling the digest; any generic HVAC filler that could appear on any city's page; any two sections of this page that blur together. Return a numbered list of concrete fixes, max 6, or "OK".`, { label: `unique:${city}`, phase: wavePhase }),
      () => agent(`Read ${file}. You are a demanding editorial director. Judge taste and truth: does every paragraph earn its place; is the local geography real and correctly used (flag anything dubious about ${city}); any invented business claims (${CLAIMS_POLICY}); any limp sentence a good writer would cut. Return a numbered list of concrete fixes, max 6, or "OK".`, { label: `edit:${city}`, phase: wavePhase }),
    ])

    const issues = (critiques || []).filter(Boolean).filter(c => c.trim() !== 'OK').join('\n')

    // 3. reviser (only if critics found something)
    if (issues.trim()) {
      await agent(
        `Read ${file} and apply ALL of these editorial fixes, rewriting content as needed while keeping the JSON schema intact (same keys, exactly 2 imgcol sections, valid JSON, 1,250-1,700 words):\n${issues}\n\n${CLAIMS_POLICY}\nWrite the corrected file back to ${file}. Return a 2-line summary of what changed.`,
        { label: `revise:${city}`, phase: wavePhase }
      )
    }

    // 4. publish + hard gate, with fix loop
    const gateOut = await agent(
      `Publish and gate the page for ${city}. Steps:\n1. Run: ${RUN} /scripts/v3-assemble.php /scripts/v3-city-${idx}.json\n2. Run: ${RUN} /scripts/v3-gate.php "${city}"\n3. If the gate prints FAIL, Read ${file}, fix exactly the listed reasons (word count, overlap, missing meta, claims, etc.) while keeping schema and quality, write the file, and repeat steps 1-2. Maximum 3 total gate attempts.\nIf JSON is malformed the assembler prints "Bad JSON" — fix and retry.\nReturn EXACTLY: the final gate output verbatim, then one line "DIGEST: city | masked title pattern | hook first 6 words | section topics | CTA".`,
      { label: `gate:${city}`, phase: wavePhase }
    )
    return { city, gateOut: gateOut || 'agent-lost' }
  }))

  for (const r of (results || []).filter(Boolean)) {
    const ok = r.gateOut.includes('PASS')
    ;(ok ? passed : failed).push(r.city)
    const dline = (r.gateOut.split('DIGEST:')[1] || '').trim()
    if (dline) digest += '\n' + dline
    log(`wave ${w + 1}: ${r.city} ${ok ? 'PASS' : 'FAIL'}`)
  }
  log(`wave ${w + 1}/${waves.length} done — ${passed.length} passed, ${failed.length} failed so far`)
}

return { passed: passed.length, failed, note: 'Each PASS cleared v3-gate.php: >=1000 words, >=2 images, valid blocks, unique title/opening/shingles vs whole corpus, meta description, no risky claims.' }