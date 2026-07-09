<?php
// Location page publisher v2 (no-inference variant): consumes pre-authored content JSON,
// enforces the diversity gates from the audit, publishes as native Gutenberg blocks.
// Usage: wp eval-file v2-from-json.php /scripts/v2-content-N.json
// Entry: {city, variant, page_title, headline, intro, sections[{title,body}],
//         faq?[{q,a}], quote?, checklist?[], cta}

$file = isset( $args[0] ) ? $args[0] : '';
if ( ! $file || ! file_exists( $file ) ) { echo "Content file not found: $file\n"; exit; }
$entries = json_decode( file_get_contents( $file ), true );
if ( ! is_array( $entries ) ) { echo 'Bad JSON: ' . json_last_error_msg() . "\n"; exit; }

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
    return preg_replace( '/\b[A-Z][a-zA-Z]+(, [A-Z]{2})?\b/', 'X', $t );
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
        $b[] = "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_slot( $slots['faq_heading'] ?? 'Common questions' ) . "</h3>\n<!-- /wp:heading -->";
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

// 1) demote matching v1 pages ("HVAC Services in {City}") to draft
foreach ( $entries as $en ) {
    $old = get_posts( array(
        'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 2,
        'title' => 'HVAC Services in ' . $en['city'],
    ) );
    foreach ( $old as $o ) {
        wp_update_post( array( 'ID' => $o->ID, 'post_status' => 'draft' ) );
        echo "drafted v1 #{$o->ID} ({$en['city']})\n";
    }
}

// 2) corpus state from remaining published pages (incl. earlier v2 batches)
$published = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 300 ) );
$seen_frames = array(); $seen_title_patterns = array(); $corpus_shingles = array();
foreach ( $published as $e ) {
    preg_match( '/<p[^>]*>(.*?)<\/p>/s', $e->post_content, $para );
    if ( isset( $para[1] ) ) { $seen_frames[ v2_frame( wp_strip_all_tags( $para[1] ) ) ] = 1; }
    $seen_title_patterns[ v2_title_pattern( $e->post_title ) ] = 1;
    $corpus_shingles[] = v2_shingles( $e->post_content );
}

// 3) gate + publish
$created = 0;
foreach ( $entries as $en ) {
    $variant = $en['variant'];
    $tp = v2_title_pattern( $en['page_title'] );
    $fr = v2_frame( $en['intro'] );
    $content = v2_build_blocks( $en, $variant );
    $sh = v2_shingles( $content );
    $maxJ = 0;
    foreach ( $corpus_shingles as $cs ) {
        $inter = count( array_intersect_key( $sh, $cs ) );
        $union = count( $sh ) + count( $cs ) - $inter;
        if ( $union && $inter / $union > $maxJ ) { $maxJ = $inter / $union; }
    }
    $fails = array();
    if ( isset( $seen_title_patterns[ $tp ] ) ) { $fails[] = "title-pattern '{$tp}'"; }
    if ( isset( $seen_frames[ $fr ] ) )         { $fails[] = "opening-frame '{$fr}'"; }
    if ( $maxJ > 0.10 )                          { $fails[] = sprintf( 'shingle %.2f', $maxJ ); }
    if ( $fails ) {
        echo "GATE-FAIL {$en['city']}: " . implode( ', ', $fails ) . "\n";
        continue;
    }
    $post_id = wp_insert_post( array(
        'post_title'   => $en['page_title'],
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'meta_input'   => array( '_location_city' => $en['city'], '_gen_variant' => $variant ),
    ), true );
    if ( is_wp_error( $post_id ) ) { echo "ERROR {$en['city']}: " . $post_id->get_error_message() . "\n"; continue; }
    $seen_title_patterns[ $tp ] = 1;
    $seen_frames[ $fr ] = 1;
    $corpus_shingles[] = $sh;
    $created++;
    echo "CREATED [{$variant}] {$en['city']}: \"{$en['page_title']}\"\n";
}
echo "\nDone: {$created}/" . count( $entries ) . "\n";
