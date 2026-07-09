<?php
// Demo: generate a unique location page as native Gutenberg blocks via local Ollama,
// then publish it as a WordPress page. Proof-of-pipeline for the 50-location workflow.
global $mwai;

$city = isset( $args[0] ) ? $args[0] : 'Lancaster, PA';

$prompt = <<<PROMPT
You write WordPress Gutenberg block markup. Produce a short landing page for an HVAC company serving {$city}.

Rules:
- Output ONLY raw Gutenberg block markup (HTML comments like <!-- wp:heading -->), no explanations, no markdown fences.
- Use exactly these blocks: one wp:heading (H2 with a city-specific headline), one wp:paragraph (2 sentences mentioning {$city} landmarks or neighborhoods), one wp:columns with two wp:column each containing a wp:paragraph (one about heating, one about cooling), and one wp:buttons with a single wp:button linking to /contact with text "Get a Free Quote".
- Keep total under 160 words. Make content specific to {$city}, not generic.
PROMPT;

$content = $mwai->simpleTextQuery( $prompt );

// Strip accidental code fences if the model added them anyway.
$content = preg_replace( '/^```[a-z]*\s*|```\s*$/m', '', trim( $content ) );

$post_id = wp_insert_post( array(
    'post_title'   => "HVAC Services in {$city}",
    'post_content' => $content,
    'post_status'  => 'publish',
    'post_type'    => 'page',
), true );

if ( is_wp_error( $post_id ) ) {
    echo 'ERROR: ' . $post_id->get_error_message() . "\n";
} else {
    echo "CREATED page {$post_id}: " . get_permalink( $post_id ) . "\n";
    echo "--- first 500 chars of generated blocks ---\n";
    echo substr( $content, 0, 500 ) . "\n";
}
