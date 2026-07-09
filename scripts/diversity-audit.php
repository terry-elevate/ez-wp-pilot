<?php
// Honest diversity audit of the generated location pages.
// Measures what near-duplicate detection actually looks at, not char overlap:
//  1. title/H1/slug patterns  2. word-shingle Jaccard  3. boilerplate n-grams
//  4. syntactic frames (opening words)  5. structural variety  6. CTA reuse
$pages = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    'meta_key'    => '_location_city',
    'numberposts' => 100,
) );

function tokenize( $html ) {
    $t = strtolower( wp_strip_all_tags( $html ) );
    $t = preg_replace( '/[^a-z0-9\s]/', ' ', $t );
    return preg_split( '/\s+/', trim( $t ) );
}

$data = array();
foreach ( $pages as $p ) {
    $words = tokenize( $p->post_content );
    // 5-word shingles
    $sh = array();
    for ( $i = 0; $i + 4 < count( $words ); $i++ ) {
        $sh[ implode( ' ', array_slice( $words, $i, 5 ) ) ] = 1;
    }
    // headline = first heading text; intro = first paragraph text
    preg_match( '/<h2[^>]*>(.*?)<\/h2>/s', $p->post_content, $h );
    preg_match( '/<p[^>]*>(.*?)<\/p>/s', $p->post_content, $para );
    $data[ $p->ID ] = array(
        'title'    => $p->post_title,
        'city'     => trim( str_replace( 'HVAC Services in', '', $p->post_title ) ),
        'headline' => isset( $h[1] ) ? wp_strip_all_tags( $h[1] ) : '',
        'intro'    => isset( $para[1] ) ? wp_strip_all_tags( $para[1] ) : '',
        'shingles' => $sh,
        'nwords'   => count( $words ),
        'cta'      => preg_match( '/wp-element-button[^>]*>([^<]+)</', $p->post_content, $c ) ? $c[1] : '',
    );
}
$n = count( $data );
echo "Pages audited: $n\n\n";

// ---- 1. Title / slug patterns ----
$titles = array_column( $data, 'title' );
$tpl_titles = 0;
foreach ( $titles as $t ) { if ( preg_match( '/^HVAC Services in .+$/', $t ) ) { $tpl_titles++; } }
echo "== Titles ==\n";
echo "Identical title pattern 'HVAC Services in {City}': $tpl_titles/$n\n";
echo "Distinct titles ignoring city name: " . count( array_unique( array_map( function( $t ) {
    return preg_replace( '/in .+$/', 'in X', $t ); }, $titles ) ) ) . "\n\n";

// ---- 2. Shingle Jaccard ----
$ids = array_keys( $data );
$maxJ = 0; $sumJ = 0; $pairs = 0; $worst = ''; $over30 = 0; $over15 = 0;
for ( $a = 0; $a < $n; $a++ ) {
    for ( $b = $a + 1; $b < $n; $b++ ) {
        $A = $data[ $ids[ $a ] ]['shingles'];
        $B = $data[ $ids[ $b ] ]['shingles'];
        $inter = count( array_intersect_key( $A, $B ) );
        $union = count( $A ) + count( $B ) - $inter;
        $j = $union ? $inter / $union : 0;
        $pairs++; $sumJ += $j;
        if ( $j > $maxJ ) { $maxJ = $j; $worst = $data[ $ids[ $a ] ]['city'] . ' /' . $data[ $ids[ $b ] ]['city']; }
        if ( $j > 0.30 ) { $over30++; }
        if ( $j > 0.15 ) { $over15++; }
    }
}
echo "== 5-word shingle Jaccard (near-duplicate signal; >0.3 = near-dup territory) ==\n";
printf( "avg %.3f, max %.3f (%s), pairs>0.30: %d, pairs>0.15: %d of %d\n\n", $sumJ / $pairs, $maxJ, $worst, $over30, $over15, $pairs );

// ---- 3. Boilerplate 4-grams: how many pages share the same phrase ----
$gramPages = array();
foreach ( $data as $id => $d ) {
    $words = array_keys( $d['shingles'] );
    $grams = array();
    foreach ( $d['shingles'] as $s => $_ ) {
        $w = explode( ' ', $s );
        $grams[ implode( ' ', array_slice( $w, 0, 4 ) ) ] = 1;
    }
    foreach ( $grams as $g => $_ ) {
        $gramPages[ $g ] = ( $gramPages[ $g ] ?? 0 ) + 1;
    }
}
arsort( $gramPages );
echo "== Most-reused 4-word phrases (pages containing it, of $n) ==\n";
$i = 0;
foreach ( $gramPages as $g => $c ) {
    if ( $c < 3 ) { break; }
    echo sprintf( "  %2d  %s\n", $c, $g );
    if ( ++$i >= 12 ) { break; }
}
$sharedGrams = count( array_filter( $gramPages, function( $c ) { return $c >= 5; } ) );
echo "4-grams appearing on 5+ pages: $sharedGrams\n\n";

// ---- 4. Syntactic frames: opening 3 words of headline and intro ----
echo "== Opening frames ==\n";
foreach ( array( 'headline', 'intro' ) as $fld ) {
    $frames = array();
    foreach ( $data as $d ) {
        $w = preg_split( '/\s+/', trim( strtolower( preg_replace( '/[^a-z\s]/i', '', $d[ $fld ] ) ) ) );
        $f = implode( ' ', array_slice( $w, 0, 3 ) );
        $frames[ $f ] = ( $frames[ $f ] ?? 0 ) + 1;
    }
    arsort( $frames );
    $top = array_slice( $frames, 0, 4, true );
    echo "$fld first-3-words, top patterns: ";
    foreach ( $top as $f => $c ) { echo "\"$f\" x$c; "; }
    echo "(distinct: " . count( $frames ) . "/$n)\n";
}

// ---- 5/6. Structure + CTA ----
$ctas = array_count_values( array_filter( array_column( $data, 'cta' ) ) );
arsort( $ctas );
echo "\n== CTAs ==\ndistinct: " . count( $ctas ) . "/$n, top: ";
foreach ( array_slice( $ctas, 0, 3, true ) as $c => $k ) { echo "\"$c\" x$k; "; }
$wc = array_column( $data, 'nwords' );
sort( $wc );
echo "\n\n== Length ==\nword counts: min {$wc[0]}, median " . $wc[ intdiv( $n, 2 ) ] . ", max " . end( $wc ) . "\n";
