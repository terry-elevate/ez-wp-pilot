<?php
// Multi-location page generator: fixed Gutenberg templates (always editor-valid),
// local AI fills content slots as JSON, layouts rotate per city.
// Usage: wp eval-file multi-location-gen.php "City One" "City Two" ...
global $mwai;

$cities = ! empty( $args ) ? $args : array( 'York, PA', 'Reading, PA', 'Harrisburg, PA', 'Allentown, PA', 'Bethlehem, PA' );

// Three structurally different layouts. All markup is ours => always valid core blocks.
$templates = array(

    // A: heading / intro / two columns / button
    'columns' => <<<'TPL'
<!-- wp:heading {"level":2} -->
<h2>%HEADLINE%</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>%INTRO%</p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>%F1TITLE%</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>%F1TEXT%</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3>%F2TITLE%</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>%F2TEXT%</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">%CTA%</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
TPL,

    // B: heading / quote-style intro / service list / paragraph / button
    'list' => <<<'TPL'
<!-- wp:heading {"level":2} -->
<h2>%HEADLINE%</h2>
<!-- /wp:heading -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>%INTRO%</p>
<!-- /wp:paragraph --></blockquote>
<!-- /wp:quote -->

<!-- wp:heading {"level":3} -->
<h3>%F1TITLE%</h3>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>%F1TEXT%</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>%F2TEXT%</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>%F2TITLE%</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">%CTA%</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
TPL,

    // C: intro-first / heading / group with two stacked features / button
    'stacked' => <<<'TPL'
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">%INTRO%</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>%HEADLINE%</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3} -->
<h3>%F1TITLE%</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>%F1TEXT%</p>
<!-- /wp:paragraph -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading {"level":3} -->
<h3>%F2TITLE%</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>%F2TEXT%</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact">%CTA%</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
TPL,
);

$template_keys = array_keys( $templates );
$angles = array(
    'emergency response times and reliability',
    'energy savings and system efficiency',
    'family comfort through all four seasons',
    'local expertise and community trust',
    'preventive maintenance and system longevity',
);

$created = array();
$i = 0;

foreach ( $cities as $city ) {
    $tpl_key = $template_keys[ $i % count( $template_keys ) ];
    $angle   = $angles[ $i % count( $angles ) ];
    $i++;

    $prompt = <<<PROMPT
You write copy for an HVAC company. Produce content for a landing page for {$city}, with a copy angle focused on {$angle}.

Reply with ONLY a JSON object, no markdown fences, with these exact string keys:
"headline" (city-specific H2, mention {$city}),
"intro" (2 sentences, mention a real neighborhood, landmark, or nearby town of {$city}),
"f1title" (short heading about heating),
"f1text" (2 sentences about heating, specific to the angle),
"f2title" (short heading about cooling),
"f2text" (2 sentences about cooling, specific to the angle),
"cta" (a 3-5 word call-to-action button label, not generic "Contact Us").
Vary sentence structure; do not reuse phrasing between fields.
PROMPT;

    $raw = $mwai->simpleTextQuery( $prompt );
    $raw = preg_replace( '/^```[a-z]*\s*|```\s*$/m', '', trim( $raw ) );
    $start = strpos( $raw, '{' );
    $end   = strrpos( $raw, '}' );
    if ( $start !== false && $end !== false ) {
        $raw = substr( $raw, $start, $end - $start + 1 );
    }
    $slots = json_decode( $raw, true );

    if ( ! is_array( $slots ) || empty( $slots['headline'] ) ) {
        echo "SKIP {$city}: model did not return valid JSON. Raw head: " . substr( $raw, 0, 120 ) . "\n";
        continue;
    }

    $map = array(
        '%HEADLINE%' => esc_html( $slots['headline'] ?? '' ),
        '%INTRO%'    => esc_html( $slots['intro'] ?? '' ),
        '%F1TITLE%'  => esc_html( $slots['f1title'] ?? 'Heating' ),
        '%F1TEXT%'   => esc_html( $slots['f1text'] ?? '' ),
        '%F2TITLE%'  => esc_html( $slots['f2title'] ?? 'Cooling' ),
        '%F2TEXT%'   => esc_html( $slots['f2text'] ?? '' ),
        '%CTA%'      => esc_html( $slots['cta'] ?? 'Get a Free Quote' ),
    );

    $content = strtr( $templates[ $tpl_key ], $map );

    $post_id = wp_insert_post( array(
        'post_title'   => "HVAC Services in {$city}",
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ), true );

    if ( is_wp_error( $post_id ) ) {
        echo "ERROR {$city}: " . $post_id->get_error_message() . "\n";
        continue;
    }

    $created[] = $post_id;
    echo "CREATED [{$tpl_key}] {$city} -> " . get_permalink( $post_id ) . "\n";
    echo "   headline: {$slots['headline']}\n   cta: {$slots['cta']}\n";
}

echo "\nDone: " . count( $created ) . '/' . count( $cities ) . " pages.\n";
