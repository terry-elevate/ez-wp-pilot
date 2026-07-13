<?php
// Run gate check on ALL location pages at once
$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1,
    'meta_key' => '_location_city' ) );

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

$pass = 0; $fail = 0; $all_shingles = array(); $openings = array();

foreach ( $pages as $p ) {
    $city = get_post_meta( $p->ID, '_location_city', true );
    $fails = array();

    $wc = str_word_count( wp_strip_all_tags( $p->post_content ) );
    if ( $wc < 1000 ) { $fails[] = "words {$wc} < 1000"; }

    $imgs = substr_count( $p->post_content, '<img' );
    if ( $imgs < 2 ) { $fails[] = "inline images {$imgs} < 2"; }

    preg_match_all( '/<h2[^>]*>/', $p->post_content, $hm );
    $h2s = count( $hm[0] );
    if ( $h2s < 4 ) { $fails[] = "h2s {$h2s} < 4"; }

    // paragraph length
    preg_match_all( '/<p[^>]*>(.*?)<\/p>/s', $p->post_content, $pm );
    $long = 0;
    foreach ( $pm[1] as $ptxt ) {
        if ( str_word_count( strip_tags( $ptxt ) ) > 110 ) { $long++; }
    }
    if ( $long > 0 ) { $fails[] = "{$long} paragraphs > 110 words"; }

    if ( empty( $p->post_excerpt ) && empty( get_post_meta( $p->ID, '_yoast_wpseo_metadesc', true ) ) ) {
        $fails[] = "no meta description";
    }

    // shingle cross-check
    $sh = g_sh( $p->post_content );
    foreach ( $all_shingles as $other_city => $osh ) {
        $common = count( array_intersect_key( $sh, $osh ) );
        $total = max( count( $sh ), count( $osh ) );
        if ( $total > 0 ) {
            $j = $common / $total;
            if ( $j > 0.08 ) { $fails[] = "shingle J=" . number_format($j, 3) . " with {$other_city}"; break; }
        }
    }
    $all_shingles[ $city ] = $sh;

    // opening frame uniqueness
    if ( preg_match( '/<p[^>]*>(.{3,}?)<\/p>/s', $p->post_content, $om ) ) {
        $words = explode( ' ', strip_tags( $om[1] ) );
        $opening = implode( ' ', array_slice( $words, 0, 3 ) );
        if ( in_array( $opening, $openings ) ) { $fails[] = "opening frame duplicates: '{$opening}'"; }
        $openings[] = $opening;
    }

    if ( $fails ) {
        $fail++;
        echo "FAIL {$city} (#{$p->ID}): " . implode( ', ', $fails ) . "\n";
    } else {
        $pass++;
        $maxj = 0;
        foreach ( $all_shingles as $oc => $osh ) {
            if ( $oc === $city ) continue;
            $common = count( array_intersect_key( $sh, $osh ) );
            $total = max( count( $sh ), count( $osh ) );
            if ( $total > 0 ) { $maxj = max( $maxj, $common / $total ); }
        }
        echo "PASS {$city} (#{$p->ID}) — {$wc} words, {$imgs} imgs, {$h2s} h2s, maxJ " . number_format( $maxj, 3 ) . "\n";
    }
}
echo "\n=== GATE: {$pass} PASS, {$fail} FAIL / " . count( $pages ) . " total ===\n";
