<?php
// v3: rebuild location pages to EZ production standard — 700+ words, images from the
// licensed pool, varied designed layouts — updating the existing v2 pages IN PLACE
// (same slugs/URLs). Usage: wp eval-file v3-assemble.php /scripts/v3-content-N.json
$file = isset( $args[0] ) ? $args[0] : '';
if ( ! $file || ! file_exists( $file ) ) { echo "Content file not found: $file\n"; exit; }
$entries = json_decode( file_get_contents( $file ), true );
if ( ! is_array( $entries ) ) { echo 'Bad JSON: ' . json_last_error_msg() . "\n"; exit; }

$pool = get_option( 'ez_img_pool', array() );
$img_use_counter = array();

function v3_esc( $v ) { return esc_html( trim( (string) $v ) ); }

function v3_pick_image( $topic, &$counter, $pool ) {
    if ( empty( $pool[ $topic ] ) ) { return null; }
    $i = $counter[ $topic ] ?? 0;
    $counter[ $topic ] = $i + 1;
    $id = $pool[ $topic ][ $i % count( $pool[ $topic ] ) ];
    $src = wp_get_attachment_image_src( $id, 'large' );
    if ( ! $src ) { return null; }
    return array(
        'id'   => $id,
        'url'  => $src[0],
        'alt'  => get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'cred' => get_post_meta( $id, '_ez_attribution', true ),
    );
}

function v3_img_block( $img, $caption = '' ) {
    $cap = $caption !== '' ? '<figcaption class="wp-element-caption">' . v3_esc( $caption ) . '</figcaption>' : '';
    return "<!-- wp:image {\"id\":{$img['id']},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n"
         . "<figure class=\"wp-block-image size-large\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/>{$cap}</figure>\n<!-- /wp:image -->";
}

function v3_paras( $paras ) {
    $out = array();
    foreach ( (array) $paras as $p ) {
        $out[] = "<!-- wp:paragraph -->\n<p>" . v3_esc( $p ) . "</p>\n<!-- /wp:paragraph -->";
    }
    return implode( "\n\n", $out );
}

function v3_h( $text, $level = 2 ) {
    return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>" . v3_esc( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

$updated = 0;
foreach ( $entries as $en ) {
    $existing = get_posts( array(
        'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => '_location_city', 'meta_value' => $en['city'],
    ) );
    if ( ! $existing ) { echo "NO PAGE for {$en['city']}\n"; continue; }
    $page = $existing[0];

    $credits = array();
    $b = array();

    // hero image + hook
    $hero = v3_pick_image( $en['hero_topic'], $img_use_counter, $pool );
    if ( $hero && $hero['cred'] ) { $credits[] = $hero['cred']; }
    $b[] = "<!-- wp:paragraph {\"fontSize\":\"large\"} -->\n<p class=\"has-large-font-size\">" . v3_esc( $en['hook'] ) . "</p>\n<!-- /wp:paragraph -->";
    $b[] = v3_paras( $en['intro'] );

    // sections
    foreach ( $en['sections'] as $s ) {
        $b[] = v3_h( $s['title'], 2 );
        $layout = $s['layout'] ?? 'text';
        if ( $layout === 'imgcol' && ! empty( $s['image_topic'] ) ) {
            $img = v3_pick_image( $s['image_topic'], $img_use_counter, $pool );
            $textcol = v3_paras( $s['paras'] );
            if ( $img ) {
                if ( $img['cred'] ) { $credits[] = $img['cred']; }
                $imgcol = v3_img_block( $img );
                $left  = ( $s['img_side'] ?? 'left' ) === 'left' ? $imgcol : $textcol;
                $right = ( $s['img_side'] ?? 'left' ) === 'left' ? $textcol : $imgcol;
                $b[] = "<!-- wp:columns -->\n<div class=\"wp-block-columns\"><!-- wp:column -->\n<div class=\"wp-block-column\">{$left}</div>\n<!-- /wp:column -->\n\n<!-- wp:column -->\n<div class=\"wp-block-column\">{$right}</div>\n<!-- /wp:column --></div>\n<!-- /wp:columns -->";
            } else {
                $b[] = $textcol;
            }
        } elseif ( $layout === 'textlist' ) {
            $b[] = v3_paras( $s['paras'] );
            if ( ! empty( $s['list'] ) ) {
                $items = '';
                foreach ( $s['list'] as $it ) {
                    $items .= "<!-- wp:list-item -->\n<li>" . v3_esc( $it ) . "</li>\n<!-- /wp:list-item -->\n\n";
                }
                $b[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . rtrim( $items ) . "</ul>\n<!-- /wp:list -->";
            }
        } else {
            $b[] = v3_paras( $s['paras'] );
        }
    }

    // local context
    if ( ! empty( $en['local'] ) ) {
        $b[] = v3_h( $en['local']['title'], 2 );
        $b[] = v3_paras( $en['local']['paras'] );
    }

    // FAQ
    if ( ! empty( $en['faq'] ) ) {
        $b[] = v3_h( $en['faq_heading'] ?? 'Questions we hear in ' . explode( ',', $en['city'] )[0], 2 );
        foreach ( $en['faq'] as $qa ) {
            $b[] = v3_h( $qa['q'], 3 );
            $b[] = v3_paras( array( $qa['a'] ) );
        }
    }

    // nearby-areas internal links
    if ( ! empty( $en['nearby'] ) ) {
        $nlinks = array();
        foreach ( (array) $en['nearby'] as $ncity ) {
            $np = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
                'meta_key' => '_location_city', 'meta_value' => $ncity ) );
            if ( $np ) {
                $nlinks[] = '<a href="' . esc_url( get_permalink( $np[0]->ID ) ) . '">' . esc_html( str_replace( ', PA', '', $ncity ) ) . '</a>';
            }
        }
        if ( $nlinks ) {
            $b[] = "<!-- wp:paragraph -->\n<p>Also serving nearby: " . implode( ' · ', $nlinks ) . "</p>\n<!-- /wp:paragraph -->";
        }
    }

    // closing CTA group (tinted)
    $c = $en['closing'];
    $b[] = "<!-- wp:group {\"className\":\"cta-band\",\"backgroundColor\":\"tint\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"32px\",\"bottom\":\"32px\",\"left\":\"32px\",\"right\":\"32px\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
         . "<div class=\"wp-block-group cta-band has-tint-background-color has-background\" style=\"padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px\">"
         . v3_h( $c['title'], 2 ) . "\n\n" . v3_paras( array( $c['para'] ) )
         . "\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/contact\">" . v3_esc( $c['cta'] ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->"
         . "</div>\n<!-- /wp:group -->";

    // photo credits
    if ( $credits ) {
        $b[] = "<!-- wp:paragraph {\"fontSize\":\"small\"} -->\n<p class=\"has-small-font-size\">Photos: " . v3_esc( implode( ' · ', array_unique( $credits ) ) ) . "</p>\n<!-- /wp:paragraph -->";
    }

    $content = implode( "\n\n", $b );
    $update = array( 'ID' => $page->ID, 'post_content' => $content );
    if ( ! empty( $en['meta_description'] ) ) { $update['post_excerpt'] = $en['meta_description']; }
    wp_update_post( $update );
    if ( ! empty( $en['meta_description'] ) ) {
        update_post_meta( $page->ID, '_yoast_wpseo_metadesc', $en['meta_description'] );
    }
    if ( $hero ) { set_post_thumbnail( $page->ID, $hero['id'] ); }
    update_post_meta( $page->ID, '_gen_version', 'v3' );

    $wc = str_word_count( wp_strip_all_tags( $content ) );
    $ic = substr_count( $content, '<img' );
    $updated++;
    echo "UPDATED {$en['city']} (#{$page->ID}): {$wc} words, {$ic} images — " . get_permalink( $page->ID ) . "\n";
}
echo "\nDone: {$updated}/" . count( $entries ) . "\n";
