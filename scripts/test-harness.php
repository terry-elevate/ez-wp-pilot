<?php
/**
 * Visual test harness: every component kit in every band context,
 * plus each structural section once. Catches contrast bugs before ship.
 * Run: docker compose run --rm --entrypoint "" wpcli wp eval-file /var/www/html/scripts/test-harness.php
 */

require_once '/var/www/html/scripts/v3-sections.php';

// same round-robin picker as v3-render.php
function pick_img( $topic, &$img_counter, $pool ) {
    $fallbacks = array( 'ductwork' => 'basement', 'condenser' => 'minisplit',
                        'thermostat' => 'furnace', 'filter' => 'technician' );
    if ( empty( $pool[ $topic ] ) && isset( $fallbacks[ $topic ] ) ) { $topic = $fallbacks[ $topic ]; }
    if ( empty( $pool[ $topic ] ) ) { return null; }
    $i = $img_counter[ $topic ] ?? 0;
    $img_counter[ $topic ] = $i + 1;
    $id = $pool[ $topic ][ $i % count( $pool[ $topic ] ) ];
    $src = wp_get_attachment_image_src( $id, 'large' );
    if ( ! $src ) { return null; }
    return array( 'id' => $id, 'url' => $src[0],
        'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'cred' => get_post_meta( $id, '_ez_attribution', true ) );
}

$pool = get_option( 'ez_img_pool', array() );
$counter = array();
$img = pick_img( 'technician', $counter, $pool );

// The component kit that must stay readable in every band.
function harness_kit( $label ) {
    $kit  = sec_h( "Band context: {$label} — H2 heading", 2 ) . "\n\n";
    $kit .= sec_h( 'H3 subheading in this band', 3 ) . "\n\n";
    $kit .= sec_p( 'Body paragraph. If you can read this in every band variant, paragraph color contracts hold. The next elements each declare their own colors.' ) . "\n\n";
    $kit .= sec_list( array( 'List item one', 'List item two', 'List item three' ) ) . "\n\n";
    $kit .= sec_list( array( 'Heat Pump', 'Furnace', 'Mini-Split' ), 's-pills' ) . "\n\n";
    $kit .= sec_quote( 'Pull quote — must be readable here.', 'Harness', '' ) . "\n\n";
    $kit .= sec_buttons( sec_button( 'Primary CTA' ) . sec_button( 'Outline CTA', '/contact/', true ) );
    return $kit;
}

$bands = array( '' => 'default', 'sand' => 'sand', 'cream' => 'cream', 'ink' => 'ink', 'gradient' => 'gradient', 'dark-gradient' => 'dark-gradient' );

$content = '';

// 1. Kits per band
foreach ( $bands as $variant => $label ) {
    $content .= sec_band( harness_kit( $label ), $variant ) . "\n\n";
}

// 2. Nested light-in-dark (the July 2026 bug class)
$content .= sec_band(
    sec_h( 'Ink band with a nested sand band below', 2 ) . "\n\n" . sec_band( harness_kit( 'sand nested in ink' ), 'sand' ),
    'ink'
) . "\n\n";

// 3. Structural sections once each
$content .= sec_hero_cover( $img, 'Hero cover headline', 'Hero cover subline text for contrast check.', 'Hero CTA' ) . "\n\n";
$content .= sec_hero_split( $img, 'Hero split headline', 'Hero split intro text.', 'Hero CTA' ) . "\n\n";
$content .= sec_hero_offset( $img, 'Hero offset headline', 'Hero offset intro text.', 'Hero CTA' ) . "\n\n";
$content .= sec_trust_bar( array(
    array( 'label' => '15+ Years Serving PA' ), array( 'label' => 'Licensed & Insured' ), array( 'label' => 'Same-Day Emergency' ),
) ) . "\n\n";
$content .= sec_stats( array(
    array( 'number' => '51', 'label' => 'cities' ), array( 'number' => '1,000+', 'label' => 'words per page' ), array( 'number' => '84', 'label' => 'pool images' ),
) ) . "\n\n";
$content .= sec_feature_row( 'Feature row (photo-on-top cards)', array(
    array( 'title' => 'AC Repair', 'text' => 'Emergency cooling', 'image_topic' => 'condenser' ),
    array( 'title' => 'Furnace', 'text' => 'High-efficiency heat', 'image_topic' => 'furnace' ),
    array( 'title' => 'Ductwork', 'text' => 'Sealing and routing', 'image_topic' => 'ductwork' ),
), $pool, $counter ) . "\n\n";
$content .= sec_cards( 'Cards section', array(
    array( 'title' => 'Card one', 'text' => 'Card body text.' ),
    array( 'title' => 'Card two', 'text' => 'Card body text.' ),
    array( 'title' => 'Card three', 'text' => 'Card body text.' ),
), 3, '', $pool, $counter ) . "\n\n";
$content .= sec_table( 'Table section', array( 'System', 'Cost', 'Life' ), array(
    array( 'Furnace', '$4–8k', '15–20 yr' ), array( 'Heat pump', '$8–14k', '12–15 yr' ),
) ) . "\n\n";
$content .= sec_cta_center( 'CTA center on sand', 'Closing call to action text.', 'Book Now', 'sand' ) . "\n\n";
$content .= sec_cta_center( 'CTA center on ink', 'Closing call to action text.', 'Book Now', 'ink' );

$existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'pagename' => 'visual-test-harness' ) );
$args = array(
    'post_title'   => 'Visual Test Harness — every section, every band',
    'post_name'    => 'visual-test-harness',
    'post_content' => $content,
    'post_status'  => 'publish',
    'post_type'    => 'page',
);
if ( $existing ) { $args['ID'] = $existing[0]->ID; }
$pid = wp_insert_post( $args );
update_post_meta( $pid, '_location_city', 'Harness, PA' ); // triggers location.css enqueue
update_post_meta( $pid, '_wp_page_template', 'location' );
echo "Harness page: " . get_permalink( $pid ) . "\n";
