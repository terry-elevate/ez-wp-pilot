<?php
// Enforce EZ Marketing internal standards on every generated location page:
//  1. CTA required on the page (button block present)
//  2. No text walls (no single paragraph/list item over 90 words)
//  3. Proper heading present (H2)
//  4. Layout breakup (columns, list, or group block used)
// Also reports block validity and aggregate similarity stats across all pages.
$pages = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    'meta_key'    => '_location_city',
    'numberposts' => 100,
) );

$fail_pages = 0;
$texts      = array();
$invalid_total = 0;

foreach ( $pages as $p ) {
    $blocks   = parse_blocks( $p->post_content );
    $names    = array();
    $invalid  = 0;
    $walk = function( $blocks ) use ( &$walk, &$names, &$invalid ) {
        foreach ( $blocks as $b ) {
            if ( ! empty( $b['blockName'] ) ) {
                $names[] = $b['blockName'];
            } elseif ( trim( $b['innerHTML'] ) !== '' ) {
                $invalid++;
            }
            if ( ! empty( $b['innerBlocks'] ) ) {
                $walk( $b['innerBlocks'] );
            }
        }
    };
    $walk( $blocks );

    $has_cta     = in_array( 'core/button', $names, true ) || strpos( $p->post_content, 'wp-block-button__link' ) !== false;
    $has_heading = in_array( 'core/heading', $names, true );
    // layout breakup = structural blocks, or heading-dense Q&A style (3+ headings splitting the text)
    $heading_count = count( array_keys( $names, 'core/heading', true ) );
    $has_layout  = (bool) array_intersect( array( 'core/columns', 'core/list', 'core/group' ), $names ) || $heading_count >= 3;

    // text-wall check: longest paragraph / list item in words
    preg_match_all( '/<(p|li)[^>]*>(.*?)<\/\1>/s', $p->post_content, $m );
    $max_words = 0;
    foreach ( $m[2] as $chunk ) {
        $w = str_word_count( wp_strip_all_tags( $chunk ) );
        if ( $w > $max_words ) { $max_words = $w; }
    }
    $no_wall = $max_words <= 90;

    // depth + imagery requirements (EZ's real production standard: SEO pages run 400-1000+
    // words and layouts are broken up with photography)
    $word_count = str_word_count( wp_strip_all_tags( $p->post_content ) );
    $img_count  = substr_count( $p->post_content, '<img' );
    $deep_enough = $word_count >= 600;
    $has_images  = $img_count >= 2;

    $pass = $has_cta && $has_heading && $has_layout && $no_wall && $invalid === 0 && $deep_enough && $has_images;
    if ( ! $pass ) {
        $fail_pages++;
        printf( "FAIL %-38s cta:%d heading:%d layout:%d maxwords:%d invalid:%d words:%d imgs:%d\n",
            substr( $p->post_title, 0, 38 ), $has_cta, $has_heading, $has_layout, $max_words, $invalid, $word_count, $img_count );
    }
    $invalid_total += $invalid;
    $texts[ $p->ID ] = strtolower( wp_strip_all_tags( $p->post_content ) );
}

printf( "\nStandards: %d/%d pages pass all checks (CTA, heading, layout breakup, no text walls, valid blocks)\n",
    count( $pages ) - $fail_pages, count( $pages ) );
printf( "Invalid block chunks across all pages: %d\n", $invalid_total );

// similarity stats across all pairs
$ids = array_keys( $texts );
$n = count( $ids );
$min = 101.0; $max = 0.0; $sum = 0.0; $pairs = 0; $over50 = 0;
$max_pair = '';
for ( $a = 0; $a < $n; $a++ ) {
    for ( $b = $a + 1; $b < $n; $b++ ) {
        similar_text( $texts[ $ids[ $a ] ], $texts[ $ids[ $b ] ], $pct );
        $pairs++;
        $sum += $pct;
        if ( $pct < $min ) { $min = $pct; }
        if ( $pct > $max ) { $max = $pct; $max_pair = get_the_title( $ids[ $a ] ) . ' / ' . get_the_title( $ids[ $b ] ); }
        if ( $pct > 50 ) { $over50++; }
    }
}
printf( "\nSimilarity over %d pages, %d pairs: min %.0f%%, avg %.0f%%, max %.0f%% (%s)\n",
    $n, $pairs, $min, $sum / max( 1, $pairs ), $max, $max_pair );
printf( "Pairs over 50%% (mass-production territory): %d\n", $over50 );
