<?php
// v3-render: Takes a layout spec JSON and renders the page using section library.
// Usage: wp eval-file v3-render.php /path/to/spec.json ["City, PA"]
// Spec format: { "city": "Pittsburgh, PA", "sections": [ { "type": "hero_cover", ... }, ... ] }

require_once __DIR__ . '/v3-sections.php';
require_once __DIR__ . '/pencil-designs.php';

$file = isset( $args[0] ) ? $args[0] : '';
if ( ! $file || ! file_exists( $file ) ) { echo "Spec file not found: $file\n"; exit; }
$specs = json_decode( file_get_contents( $file ), true );
if ( ! is_array( $specs ) ) { echo 'Bad JSON: ' . json_last_error_msg() . "\n"; exit; }
$only_city = isset( $args[1] ) ? trim( $args[1] ) : '';

// Normalize: single spec vs array of specs
if ( isset( $specs['city'] ) ) { $specs = array( $specs ); }

$pool = get_option( 'ez_img_pool', array() );
$img_counter = array();

function pick_img( $topic, &$img_counter, $pool ) {
    $requested = $topic;
    $fallbacks = array(
        'basement' => 'ductwork',
        'hero_winter' => 'winter',
        'hero_street' => 'suburban',
        'condenser' => 'minisplit',
        'thermostat' => 'furnace',
        'filter' => 'technician',
    );
    if ( empty( $pool[ $topic ] ) && isset( $fallbacks[ $topic ] ) ) { $topic = $fallbacks[ $topic ]; }
    if ( empty( $pool[ $topic ] ) && ! empty( $pool['suburban'] ) ) {
        error_log( "Image topic '{$requested}' missing; using suburban fallback" );
        $topic = 'suburban';
    }
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

$updated = 0;
foreach ( $specs as $spec ) {
    $spec = pencil_apply_design_contract( $spec );
    $city = $spec['city'];
    if ( $only_city && strcasecmp( $only_city, $city ) !== 0 ) { continue; }
    $existing = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => '_location_city', 'meta_value' => $city ) );
    if ( ! $existing ) { echo "NO PAGE for {$city}\n"; continue; }
    $page = $existing[0];

    $blocks = array();
    $credits = array();

    foreach ( $spec['sections'] as $sec ) {
        $type = $sec['type'];
        $band = $sec['band'] ?? null;
        $rendered = '';

        // Resolve images
        $img = null;
        if ( ! empty( $sec['image_topic'] ) ) {
            $img = pick_img( $sec['image_topic'], $img_counter, $pool );
            if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }
        }

        switch ( $type ) {
            case 'hero_pencil_split':
                $rendered = sec_hero_pencil_split(
                    $img,
                    $sec['eyebrow'] ?? '',
                    $sec['headline'],
                    $sec['text'] ?? $sec['subline'] ?? '',
                    $sec['cta'] ?? 'Get an Assessment'
                );
                break;
            case 'hero_cover':
                $rendered = sec_hero_cover( $img, $sec['headline'], $sec['subline'] ?? $sec['text'] ?? '', $sec['cta'] ?? 'Get an Assessment' );
                break;
            case 'hero_split':
                $rendered = sec_hero_split( $img, $sec['headline'], $sec['text'] ?? '', $sec['cta'] ?? 'Get an Assessment' );
                break;
            case 'hero_text':
                $rendered = sec_hero_text( $sec['headline'], $sec['subline'] ?? '', $sec['cta'] ?? 'Get an Assessment' );
                break;
            case 'hero_offset':
                $rendered = sec_hero_offset( $img, $sec['headline'], $sec['text'] ?? '', $sec['cta'] ?? 'Get an Assessment' );
                break;

            case 'content_prose':
                $rendered = sec_content_prose( $sec['heading'], $sec['paras'] );
                break;
            case 'content_media_left':
                $rendered = sec_content_media_left( $sec['heading'], $sec['paras'], $img, $sec['list'] ?? null );
                break;
            case 'content_media_right':
                $rendered = sec_content_media_right( $sec['heading'], $sec['paras'], $img, $sec['list'] ?? null );
                break;
            case 'content_wide_img':
                $rendered = sec_content_wide_img( $sec['heading'], $sec['paras'], $img );
                break;
            case 'content_indent':
                $rendered = sec_content_indent( $sec['heading'], $sec['paras'] );
                break;
            case 'content_steps':
                $rendered = sec_content_steps( $sec['heading'], $sec['items'] );
                break;
            case 'content_icon_list':
                $rendered = sec_content_icon_list( $sec['heading'], $sec['paras'], $sec['list'] );
                break;
            case 'content_timeline':
                $rendered = sec_content_timeline( $sec['heading'], $sec['items'] );
                break;

            case 'cards':
                $rendered = sec_cards( $sec['heading'], $sec['cards'], $sec['cols'] ?? 3, $sec['variant'] ?? '', $pool, $img_counter );
                break;

            case 'table':
                $rendered = sec_table( $sec['heading'], $sec['headers'], $sec['rows'], $sec['variant'] ?? '' );
                break;

            case 'quote':
                $rendered = sec_quote( $sec['text'], $sec['cite'] ?? '', $sec['variant'] ?? 'accent' );
                break;

            case 'cta_center':
                $rendered = sec_cta_center( $sec['heading'], $sec['text'], $sec['cta'] ?? 'Get an Assessment', $sec['band'] ?? 'sand' );
                $band = null; // already banded
                break;
            case 'cta_inline':
                $rendered = sec_cta_inline( $sec['text'], $sec['link_text'] ?? 'Schedule a visit' );
                break;
            case 'cta_card':
                $rendered = sec_cta_card( $sec['heading'], $sec['text'], $sec['cta'] ?? 'Get an Assessment' );
                break;

            case 'faq':
                $rendered = sec_faq( $sec['heading'], $sec['items'], $sec['variant'] ?? '' );
                break;

            case 'diagnostic':
                $rendered = sec_diagnostic( $sec['title'], $sec['items'] );
                break;
            case 'warning':
                $rendered = sec_warning_box( $sec['title'], $sec['items'] );
                break;

            case 'pills':
                $rendered = sec_pills( $sec['items'], $sec['variant'] ?? '' );
                break;

            case 'nearby':
                $rendered = sec_nearby( $sec['cities'] ?? array() );
                break;

            case 'separator':
                $rendered = sec_sep( $sec['variant'] ?? '' );
                break;

            case 'content_overlap':
                $rendered = sec_content_overlap( $sec['heading'], $sec['paras'], $img );
                break;

            case 'stats':
                $rendered = sec_stats( $sec['items'] );
                break;

            case 'feature_row':
                $rendered = sec_feature_row( $sec['heading'], $sec['features'], $pool, $img_counter );
                break;

            case 'cta_fullbleed':
                $rendered = sec_cta_fullbleed( $sec['heading'], $sec['text'], $sec['cta'] ?? 'Get an Assessment' );
                $band = null;
                break;

            case 'trust_bar':
                $rendered = sec_trust_bar( $sec['items'] );
                $band = null;
                break;

            case 'photo_gallery':
                $rendered = sec_photo_gallery( $sec['heading'] ?? 'Our Work', $sec['topics'] ?? array('technician','furnace','suburban'), $pool, $img_counter );
                break;

            case 'split_feature':
                $rendered = sec_split_feature( $sec['heading'], $sec['features'], $img );
                break;

            default:
                $rendered = sec_wrap( sec_p( "Unknown section type: {$type}" ) );
        }

        // Wrap in band if specified and not already banded
        if ( $band && $rendered ) {
            $rendered = sec_band( $rendered, $band );
        }

        if ( $rendered ) { $blocks[] = $rendered; }
    }

    $content = implode( "\n\n", $blocks );

    // Ensure ≥2 inline images — inject if needed
    $img_count = substr_count( $content, '<img' );
    if ( $img_count < 2 ) {
        $needed = 2 - $img_count;
        $hero_topic = '';
        foreach ( $spec['sections'] as $sec ) {
            if ( ! empty( $sec['image_topic'] ) ) { $hero_topic = $sec['image_topic']; break; }
        }
        if ( ! $hero_topic ) { $hero_topic = 'technician'; }
        for ( $x = 0; $x < $needed; $x++ ) {
            $extra_img = pick_img( $hero_topic, $img_counter, $pool );
            if ( $extra_img ) {
                if ( $extra_img['cred'] ) { $credits[] = $extra_img['cred']; }
                $inject = sec_img_block( $extra_img, 'wide' );
                // Insert after the 3rd section break (between blocks)
                $parts = explode( "\n\n<!-- wp:group", $content, 4 + $x );
                if ( count( $parts ) > 3 + $x ) {
                    $last = array_pop( $parts );
                    $content = implode( "\n\n<!-- wp:group", $parts ) . "\n\n" . $inject . "\n\n<!-- wp:group" . $last;
                } else {
                    $content .= "\n\n" . $inject;
                }
            }
        }
    }

    // Photo credits
    if ( $credits ) {
        $content .= "\n\n<!-- wp:paragraph {\"className\":\"s-credits\",\"fontSize\":\"small\"} -->\n<p class=\"s-credits has-small-font-size\">Photos: " . sec_esc( implode( ' · ', array_unique( $credits ) ) ) . "</p>\n<!-- /wp:paragraph -->";
    }

    $update = array( 'ID' => $page->ID, 'post_content' => $content );
    if ( ! empty( $spec['meta_description'] ) ) { $update['post_excerpt'] = $spec['meta_description']; }
    wp_update_post( $update );
    if ( ! empty( $spec['meta_description'] ) ) {
        update_post_meta( $page->ID, '_yoast_wpseo_metadesc', $spec['meta_description'] );
    }
    // Set featured image from first hero image
    $hero_img = null;
    foreach ( $spec['sections'] as $sec ) {
        if ( strpos( $sec['type'], 'hero' ) === 0 && ! empty( $sec['image_topic'] ) ) {
            $hero_img = pick_img( $sec['image_topic'], $img_counter, $pool );
            break;
        }
    }
    if ( $hero_img ) { set_post_thumbnail( $page->ID, $hero_img['id'] ); }
    update_post_meta( $page->ID, '_gen_version', 'v3' );
    update_post_meta( $page->ID, '_layout_type', $spec['layout_type'] ?? 'custom' );
    if ( ! empty( $spec['design_family'] ) ) {
        update_post_meta( $page->ID, '_design_family', $spec['design_family'] );
    } else {
        delete_post_meta( $page->ID, '_design_family' );
    }
    if ( ! empty( $spec['brand_palette'] ) ) {
        update_post_meta( $page->ID, '_brand_palette', $spec['brand_palette'] );
    }
    if ( ! empty( $spec['layout_variant'] ) ) {
        update_post_meta( $page->ID, '_layout_variant', $spec['layout_variant'] );
    }

    $wc = str_word_count( wp_strip_all_tags( $content ) );
    $ic = substr_count( $content, '<img' );
    $updated++;
    echo "RENDERED {$city} (#{$page->ID}): {$wc} words, {$ic} images, " . count( $spec['sections'] ) . " sections\n";
}
echo "\nDone: {$updated}/" . count( $specs ) . "\n";
