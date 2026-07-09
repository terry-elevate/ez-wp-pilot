<?php
// Build a topical image pool from Openverse (commercial-use licenses only).
// Sideloads into the WP media library; stores {topic => [attachment_ids]} in option
// ez_img_pool and full attribution in each attachment's caption/meta.
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$topics = array(
    'hero_house'   => 'american house exterior porch',
    'hero_street'  => 'small town main street pennsylvania',
    'hero_winter'  => 'house winter snow roof',
    'furnace'      => 'furnace basement heating',
    'radiator'     => 'cast iron radiator',
    'thermostat'   => 'thermostat wall home',
    'minisplit'    => 'mini split heat pump',
    'condenser'    => 'air conditioning compressor unit',
    'ductwork'     => 'ductwork hvac installation',
    'boiler'       => 'boiler pipes heating system',
    'technician'   => 'hvac technician repair',
    'attic'        => 'attic insulation',
    'basement'     => 'unfinished basement',
    'woodstove'    => 'wood stove fire interior',
    'victorian'    => 'victorian house exterior',
    'rowhouse'     => 'brick rowhouse street',
    'workshop'     => 'garage workshop interior',
    'filter'       => 'hvac air filter',
);

$pool = get_option( 'ez_img_pool', array() );

foreach ( $topics as $key => $q ) {
    if ( ! empty( $pool[ $key ] ) ) { echo "skip {$key} (have " . count( $pool[ $key ] ) . ")\n"; continue; }
    $url = 'https://api.openverse.org/v1/images/?q=' . rawurlencode( $q )
         . '&license_type=commercial&per_page=10&filter_dead=true';
    $resp = wp_remote_get( $url, array( 'timeout' => 20 ) );
    if ( is_wp_error( $resp ) ) { echo "ERR {$key}: " . $resp->get_error_message() . "\n"; continue; }
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $got = 0;
    foreach ( (array) ( $body['results'] ?? array() ) as $r ) {
        if ( $got >= 2 ) { break; }
        $src = $r['url'] ?? '';
        $ext = strtolower( pathinfo( parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) { continue; }
        if ( ! empty( $r['width'] ) && $r['width'] < 800 ) { continue; }
        $att_id = media_sideload_image( $src, 0, $r['title'] ?? $q, 'id' );
        if ( is_wp_error( $att_id ) ) { continue; }
        $attribution = trim( sprintf( '"%s" by %s, %s, via Openverse',
            $r['title'] ?? 'Untitled', $r['creator'] ?? 'unknown', strtoupper( $r['license'] ?? '' ) ) );
        wp_update_post( array( 'ID' => $att_id, 'post_excerpt' => $attribution ) );
        update_post_meta( $att_id, '_ez_attribution', $attribution );
        update_post_meta( $att_id, '_ez_source_url', $r['foreign_landing_url'] ?? '' );
        update_post_meta( $att_id, '_wp_attachment_image_alt', $q );
        $pool[ $key ][] = $att_id;
        $got++;
    }
    update_option( 'ez_img_pool', $pool );
    echo "{$key}: {$got} images\n";
}

$total = 0;
foreach ( $pool as $ids ) { $total += count( $ids ); }
echo "\nPool total: {$total} images across " . count( $pool ) . " topics\n";
