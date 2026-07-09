<?php
// Location page generator v2 — fixes the diversity-audit findings:
//  - AI writes the page title (unique pattern per page, enforced)
//  - section topics drawn from a 9-topic pool, 2-3 per page, city-seeded combos
//  - 5 structure variants (columns / list / stacked / FAQ / checklist+quote)
//  - banned opening frames + stock phrases (from diversity-audit.php output)
//  - variable word budgets per page
//  - uniqueness gate: title-pattern + opening-frame + shingle check vs corpus, retry once
// Usage: wp eval-file multi-location-gen-v2.php "City, ST" ...
global $mwai;

$cities = ! empty( $args ) ? $args : array();
if ( empty( $cities ) ) { echo "No cities given.\n"; exit; }

$TOPICS = array(
    'furnace repair and replacement',
    'air conditioning and cooling',
    'indoor air quality and duct cleaning',
    'heat pump conversions',
    'maintenance plans and tune-ups',
    '24/7 emergency service',
    'smart thermostats and zoning',
    'energy costs and rebates',
    'humidity control',
);
$BUDGETS  = array( 130, 190, 250, 310 );
$VARIANTS = array( 'columns', 'list', 'stacked', 'faq', 'checklist' );
$BANNED   = "\"From the historic\", \"From the bustling\", \"Whether you are\", \"Whether you live\", "
          . "\"when you need it most\", \"don't wait for\", \"don't let\", \"we understand the unique\", "
          . "\"keep your family safe\"";

function v2_tokenize( $html ) {
    $t = strtolower( wp_strip_all_tags( $html ) );
    $t = preg_replace( '/[^a-z0-9\s]/', ' ', $t );
    return preg_split( '/\s+/', trim( $t ) );
}
function v2_shingles( $html ) {
    $w = v2_tokenize( $html );
    $sh = array();
    for ( $i = 0; $i + 4 < count( $w ); $i++ ) { $sh[ implode( ' ', array_slice( $w, $i, 5 ) ) ] = 1; }
    return $sh;
}
function v2_frame( $text ) {
    $w = preg_split( '/\s+/', trim( strtolower( preg_replace( '/[^a-z\s]/i', '', $text ) ) ) );
    return implode( ' ', array_slice( $w, 0, 3 ) );
}
function v2_title_pattern( $t ) {
    // title with capitalized words (likely the city) masked, to compare shapes
    return preg_replace( '/\b[A-Z][a-zA-Z]+(, [A-Z]{2})?\b/', 'X', $t );
}

// corpus state from already-published pages (v1 + prior v2 runs)
$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 200 ) );
$seen_frames = array(); $seen_title_patterns = array(); $corpus_shingles = array();
foreach ( $existing as $e ) {
    preg_match( '/<p[^>]*>(.*?)<\/p>/s', $e->post_content, $para );
    if ( isset( $para[1] ) ) { $seen_frames[ v2_frame( wp_strip_all_tags( $para[1] ) ) ] = 1; }
    $seen_title_patterns[ v2_title_pattern( $e->post_title ) ] = 1;
    $corpus_shingles[] = v2_shingles( $e->post_content );
}

function esc_slot( $v ) { return esc_html( trim( (string) $v ) ); }

function v2_build_blocks( $slots, $variant ) {
    $b = array();
    $b[] = "<!-- wp:heading {\"level\":2} -->\n<h2>" . esc_slot( $slots['headline'] ) . "</h2>\n<!-- /wp:heading -->";
    $intro = "<!-- wp:paragraph -->\n<p>" . esc_slot( $slots['intro'] ) . "</p>\n<!-- /wp:paragraph -->";
    if ( $variant === 'stacked' ) { array_unshift( $b, $intro ); } else { $b[] = $intro; }

    $secs = $slots['sections'];
    if ( $variant === 'columns' && count( $secs ) >= 2 ) {
        $cols = '';
        foreach ( array_slice( $secs, 0, 3 ) as $s ) {
            $cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $s['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_slot( $s['body'] ) . "</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:column -->\n\n";
        }
        $b[] = "<!-- wp:columns -->\n<div class=\"wp-block-columns\">" . rtrim( $cols ) . "</div>\n<!-- /wp:columns -->";
    } elseif ( $variant === 'list' ) {
        $b[] = "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $secs[0]['title'] ) . "</h3>\n<!-- /wp:heading -->";
        $items = '';
        foreach ( $secs as $s ) {
            $items .= "<!-- wp:list-item -->\n<li><strong>" . esc_slot( $s['title'] ) . ":</strong> " . esc_slot( $s['body'] ) . "</li>\n<!-- /wp:list-item -->\n\n";
        }
        $b[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . rtrim( $items ) . "</ul>\n<!-- /wp:list -->";
    } elseif ( $variant === 'faq' && ! empty( $slots['faq'] ) ) {
        foreach ( $secs as $s ) {
            $b[] = "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $s['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_slot( $s['body'] ) . "</p>\n<!-- /wp:paragraph -->";
        }
        $b[] = "<!-- wp:heading {\"level\":3} -->\n<h3>Common questions in " . esc_slot( $slots['city_short'] ) . "</h3>\n<!-- /wp:heading -->";
        foreach ( array_slice( $slots['faq'], 0, 3 ) as $qa ) {
            $b[] = "<!-- wp:paragraph -->\n<p><strong>" . esc_slot( $qa['q'] ) . "</strong><br>" . esc_slot( $qa['a'] ) . "</p>\n<!-- /wp:paragraph -->";
        }
    } elseif ( $variant === 'checklist' ) {
        if ( ! empty( $slots['quote'] ) ) {
            $b[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>" . esc_slot( $slots['quote'] ) . "</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->";
        }
        foreach ( $secs as $s ) {
            $b[] = "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $s['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_slot( $s['body'] ) . "</p>\n<!-- /wp:paragraph -->";
        }
        if ( ! empty( $slots['checklist'] ) ) {
            $items = '';
            foreach ( array_slice( $slots['checklist'], 0, 5 ) as $it ) {
                $items .= "<!-- wp:list-item -->\n<li>" . esc_slot( $it ) . "</li>\n<!-- /wp:list-item -->\n\n";
            }
            $b[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . rtrim( $items ) . "</ul>\n<!-- /wp:list -->";
        }
    } else { // stacked
        $parts = '';
        foreach ( $secs as $i => $s ) {
            if ( $i > 0 ) { $parts .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n"; }
            $parts .= "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $s['title'] ) . "</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>" . esc_slot( $s['body'] ) . "</p>\n<!-- /wp:paragraph -->\n\n";
        }
        $b[] = "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . rtrim( $parts ) . "</div>\n<!-- /wp:group -->";
    }

    $b[] = "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/contact\">" . esc_slot( $slots['cta'] ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->";
    return implode( "\n\n", $b );
}

$created = 0;
foreach ( $cities as $i => $city ) {
    $variant = $VARIANTS[ $i % count( $VARIANTS ) ];
    $budget  = $BUDGETS[ $i % count( $BUDGETS ) ];
    // city-seeded topic combo: 2-3 topics, sliding window over pool
    $tcount  = 2 + ( $i % 2 );
    $topics  = array();
    for ( $t = 0; $t < $tcount; $t++ ) { $topics[] = $TOPICS[ ( $i * 2 + $t * 3 ) % count( $TOPICS ) ]; }
    $city_short = trim( explode( ',', $city )[0] );

    $extra = '';
    if ( $variant === 'faq' )       { $extra = '"faq": [ {"q": string, "a": string (1-2 sentences)} x3 ],'; }
    if ( $variant === 'checklist' ) { $extra = '"quote": string (a one-sentence homeowner-voice quote, no attribution), "checklist": [ 4-5 short actionable tips as strings ],'; }

    for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
        $prompt = <<<PROMPT
You write landing-page copy for an HVAC company serving {$city}. This page focuses ONLY on: {$topics[0]}
PROMPT;
        $prompt .= $tcount > 1 ? ' and ' . implode( ' and ', array_slice( $topics, 1 ) ) . '.' : '.';
        $prompt .= <<<PROMPT


Total length target: about {$budget} words across all fields.

Reply with ONLY a JSON object, no markdown fences:
{"page_title": string (SEO title mentioning {$city}; must NOT start with "HVAC" and must not follow the pattern "<Service> in <City>"; vary grammar: question, benefit claim, local hook, etc.),
 "headline": string (H2, different wording than page_title),
 "intro": string (2-3 sentences naming a real neighborhood, street, landmark or nearby town of {$city}),
 "sections": [ {"title": string, "body": string (2-3 sentences)} — one per focus topic ],
 {$extra}
 "cta": string (3-6 word button label, specific to the page focus)}

Hard constraints:
- NEVER open any field with these frames or use these phrases: {$BANNED}.
- Do not mention services outside the focus topics.
- Vary sentence length; write like a specific local business, not a brochure.
PROMPT;

        $raw = $mwai->simpleTextQuery( $prompt );
        $raw = preg_replace( '/^```[a-z]*\s*|```\s*$/m', '', trim( $raw ) );
        $s = strpos( $raw, '{' ); $e = strrpos( $raw, '}' );
        if ( $s !== false && $e !== false ) { $raw = substr( $raw, $s, $e - $s + 1 ); }
        $slots = json_decode( $raw, true );

        if ( ! is_array( $slots ) || empty( $slots['page_title'] ) || empty( $slots['sections'] ) ) {
            echo "RETRY {$city} (attempt {$attempt}): bad JSON\n";
            continue;
        }
        $slots['city_short'] = $city_short;

        // uniqueness gate
        $tp = v2_title_pattern( $slots['page_title'] );
        $fr = v2_frame( $slots['intro'] );
        $content = v2_build_blocks( $slots, $variant );
        $sh = v2_shingles( $content );
        $maxJ = 0;
        foreach ( $corpus_shingles as $cs ) {
            $inter = count( array_intersect_key( $sh, $cs ) );
            $union = count( $sh ) + count( $cs ) - $inter;
            if ( $union && $inter / $union > $maxJ ) { $maxJ = $inter / $union; }
        }
        if ( isset( $seen_title_patterns[ $tp ] ) || isset( $seen_frames[ $fr ] ) || $maxJ > 0.10 ) {
            echo "RETRY {$city} (attempt {$attempt}): uniqueness gate (title-pattern dup: " . ( isset( $seen_title_patterns[ $tp ] ) ? 'y' : 'n' ) . ", frame dup: " . ( isset( $seen_frames[ $fr ] ) ? 'y' : 'n' ) . sprintf( ", maxJ %.2f)\n", $maxJ );
            continue;
        }

        $post_id = wp_insert_post( array(
            'post_title'   => wp_strip_all_tags( $slots['page_title'] ),
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ), true );
        if ( is_wp_error( $post_id ) ) { echo "ERROR {$city}: " . $post_id->get_error_message() . "\n"; break; }

        $seen_title_patterns[ $tp ] = 1;
        $seen_frames[ $fr ] = 1;
        $corpus_shingles[] = $sh;
        $created++;
        echo "CREATED [{$variant}/{$budget}w] {$city}: \"{$slots['page_title']}\"\n";
        break;
    }
}
echo "\nDone: {$created}/" . count( $cities ) . "\n";
