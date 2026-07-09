<?php
// Validate generated location pages: block structure per page + pairwise text similarity.
$pages = get_posts( array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    's'           => 'HVAC Services in',
    'numberposts' => 20,
) );

$texts = array();
foreach ( $pages as $p ) {
    $blocks  = parse_blocks( $p->post_content );
    $names   = array();
    $invalid = 0;
    foreach ( $blocks as $b ) {
        if ( ! empty( $b['blockName'] ) ) {
            $names[] = str_replace( 'core/', '', $b['blockName'] );
        } elseif ( trim( $b['innerHTML'] ) !== '' ) {
            $invalid++;
        }
    }
    $texts[ $p->post_title ] = strtolower( wp_strip_all_tags( $p->post_content ) );
    printf( "%-40s blocks: %-40s unparsed-html-chunks: %d\n", substr( $p->post_title, 17 ), implode( ',', $names ), $invalid );
}

echo "\nPairwise text similarity (similar_text %):\n";
$titles = array_keys( $texts );
for ( $a = 0; $a < count( $titles ); $a++ ) {
    for ( $b = $a + 1; $b < count( $titles ); $b++ ) {
        similar_text( $texts[ $titles[ $a ] ], $texts[ $titles[ $b ] ], $pct );
        printf( "  %-28s vs %-28s %.0f%%\n", substr( $titles[ $a ], 17 ), substr( $titles[ $b ], 17 ), $pct );
    }
}
