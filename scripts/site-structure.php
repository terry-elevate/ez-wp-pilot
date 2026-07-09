<?php
// Site structure for the Keystone Comfort demo: service-areas directory, contact page,
// featured images for all location pages, curated navigation post.

// 1) featured images for every location page (round-robin from hero pools by variant)
$pool = get_option( 'ez_img_pool', array() );
$hero_cycle = array_merge( $pool['hero_house'] ?? array(), $pool['hero_street'] ?? array(),
    $pool['victorian'] ?? array(), $pool['rowhouse'] ?? array(), $pool['hero_winter'] ?? array() );
$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish',
    'meta_key' => '_location_city', 'numberposts' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) );
$i = 0; $set = 0;
foreach ( $pages as $p ) {
    if ( ! has_post_thumbnail( $p->ID ) && $hero_cycle ) {
        set_post_thumbnail( $p->ID, $hero_cycle[ $i % count( $hero_cycle ) ] );
        $i++; $set++;
    }
}
echo "featured images set: {$set}\n";

// 2) service-areas directory page: intro + region-grouped link columns
$links = array();
foreach ( $pages as $p ) {
    $city = get_post_meta( $p->ID, '_location_city', true );
    $links[ $city ] = get_permalink( $p->ID );
}
ksort( $links );
$chunks = array_chunk( $links, (int) ceil( count( $links ) / 3 ), true );
$cols = '';
foreach ( $chunks as $chunk ) {
    $items = '';
    foreach ( $chunk as $city => $url ) {
        $label = esc_html( str_replace( ', PA', '', $city ) );
        $items .= "<!-- wp:list-item -->\n<li><a href=\"" . esc_url( $url ) . "\">{$label}</a></li>\n<!-- /wp:list-item -->\n\n";
    }
    $cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\"><!-- wp:list -->\n<ul class=\"wp-block-list\">" . rtrim( $items ) . "</ul>\n<!-- /wp:list --></div>\n<!-- /wp:column -->\n\n";
}
$sa_content = "<!-- wp:paragraph {\"fontSize\":\"large\"} -->\n<p class=\"has-large-font-size\">Fifty-one Pennsylvania communities, each with its own housing stock, climate quirks, and page written for how people actually live there.</p>\n<!-- /wp:paragraph -->\n\n"
    . "<!-- wp:columns -->\n<div class=\"wp-block-columns\">" . rtrim( $cols ) . "</div>\n<!-- /wp:columns -->";

$sa = get_page_by_path( 'service-areas' );
if ( $sa ) {
    wp_update_post( array( 'ID' => $sa->ID, 'post_content' => $sa_content ) );
    $sa_id = $sa->ID;
} else {
    $sa_id = wp_insert_post( array( 'post_title' => 'Service Areas', 'post_name' => 'service-areas',
        'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $sa_content ) );
}
echo "service-areas page: {$sa_id}\n";

// 3) contact page
$contact_content = "<!-- wp:paragraph {\"fontSize\":\"large\"} -->\n<p class=\"has-large-font-size\">Tell us what the house is doing and we'll tell you what it needs — starting with a measurement visit, not a pitch.</p>\n<!-- /wp:paragraph -->\n\n"
    . "<!-- wp:paragraph -->\n<p>Call <strong>(717) 555-0148</strong> weekdays, or use the form below. For the pilot demo, the form is a placeholder — production sites would run Gravity Forms here per EZ's standard stack.</p>\n<!-- /wp:paragraph -->\n\n"
    . "<!-- wp:paragraph {\"fontSize\":\"small\"} -->\n<p class=\"has-small-font-size\">Keystone Comfort Co. is a fictional demonstration brand. Phone number is a reserved fictional (555) number.</p>\n<!-- /wp:paragraph -->";
$ct = get_page_by_path( 'contact' );
if ( $ct ) {
    wp_update_post( array( 'ID' => $ct->ID, 'post_content' => $contact_content ) );
    $ct_id = $ct->ID;
} else {
    $ct_id = wp_insert_post( array( 'post_title' => 'Contact', 'post_name' => 'contact',
        'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $contact_content ) );
}
echo "contact page: {$ct_id}\n";

// 4) curated navigation
$nav_content = "<!-- wp:navigation-link {\"label\":\"Home\",\"url\":\"/\"} /-->\n"
    . "<!-- wp:navigation-link {\"label\":\"Service Areas\",\"url\":\"" . esc_url( get_permalink( $sa_id ) ) . "\"} /-->\n"
    . "<!-- wp:navigation-link {\"label\":\"Contact\",\"url\":\"" . esc_url( get_permalink( $ct_id ) ) . "\"} /-->";
$existing_nav = get_posts( array( 'post_type' => 'wp_navigation', 'name' => 'keystone-main', 'post_status' => 'publish', 'numberposts' => 1 ) );
if ( $existing_nav ) {
    wp_update_post( array( 'ID' => $existing_nav[0]->ID, 'post_content' => $nav_content ) );
    $nav_id = $existing_nav[0]->ID;
} else {
    $nav_id = wp_insert_post( array( 'post_title' => 'Keystone Main', 'post_name' => 'keystone-main',
        'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_content' => $nav_content ) );
}
echo "NAV_ID={$nav_id}\n";

// 5) site identity
update_option( 'blogname', 'Keystone Comfort Co.' );
update_option( 'blogdescription', 'Heating & cooling for Pennsylvania\'s real houses' );
echo "identity set\n";
