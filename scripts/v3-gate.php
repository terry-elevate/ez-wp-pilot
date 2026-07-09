<?php
// Hard quality gate for one location page. Usage: wp eval-file v3-gate.php "City, PA"
// Prints PASS or FAIL with reasons. The agentic loop must clear this before a page counts.
$city = isset( $args[0] ) ? $args[0] : '';
$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
    'meta_key' => '_location_city', 'meta_value' => $city ) );
if ( ! $pages ) { echo "FAIL: no published page for {$city}\n"; exit; }
$p = $pages[0];

function g_tok( $html ) {
    $t = strtolower( wp_strip_all_tags( $html ) );
    $t = preg_replace( '/[^a-z0-9\s]/', ' ', $t );
    return preg_split( '/\s+/', trim( $t ) );
}
function g_sh( $html ) {
    $w = g_tok( $html ); $sh = array();
    for ( $i = 0; $i + 4 < count( $w ); $i++ ) { $sh[ implode( ' ', array_slice( $w, $i, 5 ) ) ] = 1; }
    return $sh;
}

$fails = array();
$warn  = array();

// depth
$wc = str_word_count( wp_strip_all_tags( $p->post_content ) );
if ( $wc < 1000 ) { $fails[] = "words {$wc} < 1000"; }

// imagery
$imgs = substr_count( $p->post_content, '<img' );
if ( $imgs < 2 ) { $fails[] = "inline images {$imgs} < 2"; }
if ( ! has_post_thumbnail( $p->ID ) ) { $fails[] = 'no featured image'; }

// block validity (parser level)
$invalid = 0;
$walk = function( $bs ) use ( &$walk, &$invalid ) {
    foreach ( $bs as $b ) {
        if ( empty( $b['blockName'] ) && trim( $b['innerHTML'] ) !== '' ) { $invalid++; }
        if ( ! empty( $b['innerBlocks'] ) ) { $walk( $b['innerBlocks'] ); }
    }
};
$walk( parse_blocks( $p->post_content ) );
if ( $invalid > 0 ) { $fails[] = "{$invalid} invalid block chunks"; }

// text walls
preg_match_all( '/<(p|li)[^>]*>(.*?)<\/\1>/s', $p->post_content, $m );
$maxw = 0;
foreach ( $m[2] as $chunk ) { $w = str_word_count( wp_strip_all_tags( $chunk ) ); if ( $w > $maxw ) { $maxw = $w; } }
if ( $maxw > 110 ) { $fails[] = "text wall: {$maxw}-word paragraph"; }

// CTA + headings + structure
if ( strpos( $p->post_content, 'wp-block-button__link' ) === false ) { $fails[] = 'no CTA button'; }
$h2s = substr_count( $p->post_content, '<h2' );
if ( $h2s < 4 ) { $fails[] = "only {$h2s} h2 sections (<4)"; }

// SEO meta
$metadesc = get_post_meta( $p->ID, '_yoast_wpseo_metadesc', true );
if ( ! $metadesc ) { $fails[] = 'no meta description'; }
elseif ( strlen( $metadesc ) > 165 ) { $warn[] = 'meta description over 165 chars'; }
if ( stripos( $p->post_content, 'Also serving nearby' ) === false ) { $warn[] = 'no internal nearby links'; }

// uniqueness vs every other published location page
$others = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish',
    'meta_key' => '_location_city', 'numberposts' => 100, 'exclude' => array( $p->ID ) ) );
$mine = g_sh( $p->post_content );
$maxJ = 0; $worst = '';
preg_match( '/<p[^>]*>(.*?)<\/p>/s', $p->post_content, $pm );
$myframe = implode( ' ', array_slice( preg_split( '/\s+/', trim( strtolower( preg_replace( '/[^a-z\s]/i', '', wp_strip_all_tags( $pm[1] ?? '' ) ) ) ) ), 0, 3 ) );
$mypattern = preg_replace( '/\b[A-Z][a-zA-Z]+(, [A-Z]{2})?\b/', 'X', $p->post_title );
foreach ( $others as $o ) {
    $osh = g_sh( $o->post_content );
    $inter = count( array_intersect_key( $mine, $osh ) );
    $union = count( $mine ) + count( $osh ) - $inter;
    $j = $union ? $inter / $union : 0;
    if ( $j > $maxJ ) { $maxJ = $j; $worst = $o->post_title; }
    preg_match( '/<p[^>]*>(.*?)<\/p>/s', $o->post_content, $om );
    $oframe = implode( ' ', array_slice( preg_split( '/\s+/', trim( strtolower( preg_replace( '/[^a-z\s]/i', '', wp_strip_all_tags( $om[1] ?? '' ) ) ) ) ), 0, 3 ) );
    if ( $oframe === $myframe ) { $fails[] = "opening frame duplicates '{$o->post_title}'"; }
    if ( preg_replace( '/\b[A-Z][a-zA-Z]+(, [A-Z]{2})?\b/', 'X', $o->post_title ) === $mypattern ) { $fails[] = "title pattern duplicates '{$o->post_title}'"; }
}
if ( $maxJ > 0.08 ) { $fails[] = sprintf( 'shingle overlap %.3f vs "%s" (>0.08)', $maxJ, $worst ); }

// fabricated-claims scan (phrases a demo brand must not promise)
foreach ( array( '24/7', 'twenty-four hours', 'guaranteed response', 'lowest price', 'licensed and insured', 'free estimate' ) as $claim ) {
    if ( stripos( $p->post_content, $claim ) !== false ) { $fails[] = "risky claim: \"{$claim}\""; }
}

printf( "%s %s (#%d) — %d words, %d imgs, %d h2s, maxJ %.3f\n",
    $fails ? 'FAIL' : 'PASS', $city, $p->ID, $wc, $imgs, $h2s, $maxJ );
foreach ( $fails as $f ) { echo "  - {$f}\n"; }
foreach ( $warn as $w )  { echo "  ~ {$w}\n"; }
