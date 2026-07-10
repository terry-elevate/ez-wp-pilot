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
    $fallbacks = array( 'ductwork' => 'basement', 'condenser' => 'minisplit',
                        'thermostat' => 'furnace', 'filter' => 'technician' );
    if ( empty( $pool[ $topic ] ) && isset( $fallbacks[ $topic ] ) ) { $topic = $fallbacks[ $topic ]; }
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

function v3_split_long( $text ) {
    if ( str_word_count( $text ) <= 80 ) { return array( $text ); }
    $sentences = preg_split( '/(?<=[.!?;])\s+/', trim( $text ) );
    $chunks = array(); $buf = '';
    foreach ( $sentences as $s ) {
        $test = $buf ? $buf . ' ' . $s : $s;
        if ( str_word_count( $test ) > 70 && $buf !== '' ) {
            $chunks[] = $buf;
            $buf = $s;
        } else {
            $buf = $test;
        }
    }
    if ( $buf ) { $chunks[] = $buf; }
    $final = array();
    foreach ( $chunks as $c ) {
        while ( str_word_count( $c ) > 80 ) {
            $cw = preg_split( '/\s+/', trim( $c ) );
            $split_at = 70;
            for ( $i = min( 79, count($cw) - 1 ); $i >= 55; $i-- ) {
                if ( preg_match( '/[,;:\x{2014}.!?]$/u', $cw[$i] ) ) { $split_at = $i + 1; break; }
            }
            $final[] = implode( ' ', array_slice( $cw, 0, $split_at ) );
            $c = implode( ' ', array_slice( $cw, $split_at ) );
        }
        if ( trim( $c ) ) { $final[] = $c; }
    }
    return $final;
}

function v3_paras( $paras ) {
    $out = array();
    foreach ( (array) $paras as $p ) {
        foreach ( v3_split_long( $p ) as $chunk ) {
            $out[] = "<!-- wp:paragraph -->\n<p>" . v3_esc( $chunk ) . "</p>\n<!-- /wp:paragraph -->";
        }
    }
    return implode( "\n\n", $out );
}

function v3_h( $text, $level = 2 ) {
    return "<!-- wp:heading {\"level\":{$level}} -->\n<h{$level}>" . v3_esc( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

$updated = 0;
$city_idx = 0;
foreach ( $entries as $en ) {
    $existing = get_posts( array(
        'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => '_location_city', 'meta_value' => $en['city'],
    ) );
    if ( ! $existing ) { echo "NO PAGE for {$en['city']}\n"; continue; }
    $page = $existing[0];
    $city_idx++;

    // Layout variant based on city position (creates structural diversity)
    $variant = $city_idx % 4; // 0-3: four distinct layout patterns
    $qc_after_sec = ( $variant === 0 ) ? 2 : ( ( $variant === 1 ) ? 3 : ( ( $variant === 2 ) ? 1 : 2 ) );
    $mid_cta_sec = $qc_after_sec + 1;
    $kf_position = ( $variant < 2 ) ? 'after_intro' : 'after_first_section';
    $cta_color = ( $variant % 2 === 0 ) ? 'tint' : 'pale-blue';
    $faq_style = ( $variant < 2 ) ? 'flat' : 'grouped';

    $credits = array();
    $b = array();

    // hero image + hook
    $hero = v3_pick_image( $en['hero_topic'], $img_use_counter, $pool );
    if ( $hero && $hero['cred'] ) { $credits[] = $hero['cred']; }
    $b[] = "<!-- wp:paragraph {\"fontSize\":\"large\"} -->\n<p class=\"has-large-font-size\">" . v3_esc( $en['hook'] ) . "</p>\n<!-- /wp:paragraph -->";
    $b[] = v3_paras( $en['intro'] );

    // key facts bullet list — position varies by layout variant
    $kf_block = '';
    if ( ! empty( $en['key_facts'] ) ) {
        $items = '';
        foreach ( $en['key_facts'] as $fact ) {
            $items .= "<!-- wp:list-item -->\n<li>" . v3_esc( $fact ) . "</li>\n<!-- /wp:list-item -->\n\n";
        }
        $kf_block = "<!-- wp:list {\"className\":\"key-facts\"} -->\n<ul class=\"wp-block-list key-facts\">" . rtrim( $items ) . "</ul>\n<!-- /wp:list -->";
    }
    if ( $kf_position === 'after_intro' && $kf_block ) { $b[] = $kf_block; $kf_block = ''; }

    // sections (quick-check and mid-CTA positions vary by layout variant)
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        // Insert key-facts after first section for variant 2/3
        if ( $sec_i === 2 && $kf_block ) { $b[] = $kf_block; $kf_block = ''; }
        if ( $sec_i === ( $qc_after_sec + 1 ) && ! empty( $en['quick_check'] ) ) {
            $qc = $en['quick_check'];
            $qcitems = '';
            foreach ( $qc['items'] as $qi ) {
                $qcitems .= "<!-- wp:list-item -->\n<li>" . v3_esc( $qi ) . "</li>\n<!-- /wp:list-item -->\n\n";
            }
            $b[] = "<!-- wp:group {\"className\":\"quick-check\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"24px\",\"bottom\":\"24px\",\"left\":\"24px\",\"right\":\"24px\"}},\"border\":{\"left\":{\"color\":\"#0073aa\",\"width\":\"4px\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
                 . "<div class=\"wp-block-group quick-check\" style=\"border-left:4px solid #0073aa;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px\">"
                 . v3_h( $qc['title'], 3 ) . "\n\n"
                 . "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . rtrim( $qcitems ) . "</ul>\n<!-- /wp:list -->"
                 . "</div>\n<!-- /wp:group -->";
        }
        if ( $sec_i === ( $mid_cta_sec + 1 ) && ! empty( $en['mid_cta'] ) ) {
            $link_text = ! empty( $en['mid_cta_link'] ) ? $en['mid_cta_link'] : 'Schedule a visit';
            $b[] = "<!-- wp:paragraph {\"className\":\"mid-cta\",\"style\":{\"typography\":{\"fontStyle\":\"italic\"}}} -->\n<p class=\"mid-cta\" style=\"font-style:italic\">" . v3_esc( $en['mid_cta'] ) . " <a href=\"/contact\">" . v3_esc( $link_text ) . "</a>.</p>\n<!-- /wp:paragraph -->";
        }
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
                $img_left = ( $s['img_side'] ?? 'left' ) === 'left';
                $lw = $img_left ? '38%' : '60%';
                $rw = $img_left ? '60%' : '38%';
                $lv = $img_left ? ' is-vertically-aligned-center' : '';
                $rv = $img_left ? '' : ' is-vertically-aligned-center';
                $la = $img_left ? ',\"verticalAlignment\":\"center\"' : '';
                $ra = $img_left ? '' : ',\"verticalAlignment\":\"center\"';
                $b[] = "<!-- wp:columns {\"style\":{\"spacing\":{\"blockGap\":{\"left\":\"2rem\"}}}} -->\n<div class=\"wp-block-columns\">\n\n"
                     . "<!-- wp:column {\"width\":\"{$lw}\"{$la}} -->\n<div class=\"wp-block-column{$lv}\" style=\"flex-basis:{$lw}\">\n{$left}\n</div>\n<!-- /wp:column -->\n\n"
                     . "<!-- wp:column {\"width\":\"{$rw}\"{$ra}} -->\n<div class=\"wp-block-column{$rv}\" style=\"flex-basis:{$rw}\">\n{$right}\n</div>\n<!-- /wp:column -->\n\n"
                     . "</div>\n<!-- /wp:columns -->";
            } else {
                $b[] = $textcol;
            }
        } elseif ( $layout === 'textlist' ) {
            $b[] = v3_paras( $s['paras'] );
            if ( ! empty( $s['list'] ) ) {
                $items = '';
                foreach ( $s['list'] as $it ) {
                    foreach ( v3_split_long( $it ) as $chunk ) {
                        $items .= "<!-- wp:list-item -->\n<li>" . v3_esc( $chunk ) . "</li>\n<!-- /wp:list-item -->\n\n";
                    }
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

    // FAQ — grouped variant wraps in a bordered container
    if ( ! empty( $en['faq'] ) ) {
        $faq_heading = $en['faq_heading'] ?? 'Questions we hear in ' . explode( ',', $en['city'] )[0];
        if ( $faq_style === 'grouped' ) {
            $faq_inner = v3_h( $faq_heading, 2 ) . "\n\n";
            foreach ( $en['faq'] as $qa ) {
                $faq_inner .= v3_h( $qa['q'], 3 ) . "\n\n" . v3_paras( array( $qa['a'] ) ) . "\n\n";
            }
            $b[] = "<!-- wp:group {\"className\":\"faq-section\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"24px\",\"bottom\":\"24px\",\"left\":\"24px\",\"right\":\"24px\"}},\"border\":{\"top\":{\"color\":\"#dddddd\",\"width\":\"1px\"},\"bottom\":{\"color\":\"#dddddd\",\"width\":\"1px\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
                 . "<div class=\"wp-block-group faq-section\" style=\"border-top:1px solid #dddddd;border-bottom:1px solid #dddddd;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px\">"
                 . $faq_inner
                 . "</div>\n<!-- /wp:group -->";
        } else {
            $b[] = v3_h( $faq_heading, 2 );
            foreach ( $en['faq'] as $qa ) {
                $b[] = v3_h( $qa['q'], 3 );
                $b[] = v3_paras( array( $qa['a'] ) );
            }
        }
    }

    // nearby-areas internal links (styled as a proper section)
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
            $b[] = "<!-- wp:paragraph -->\n<p><strong>Serving nearby areas:</strong> " . implode( ' · ', $nlinks ) . "</p>\n<!-- /wp:paragraph -->";
        }
    }

    // closing CTA group — variant colors for structural diversity
    $c = $en['closing'];
    $cta_alt = $en['cta_alt'] ?? 'Or call us — we answer questions before scheduling anything.';
    $bg_class = ( $cta_color === 'pale-blue' ) ? 'pale-blue' : 'tint';
    $b[] = "<!-- wp:group {\"className\":\"cta-band\",\"backgroundColor\":\"{$bg_class}\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"32px\",\"bottom\":\"32px\",\"left\":\"32px\",\"right\":\"32px\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
         . "<div class=\"wp-block-group cta-band has-{$bg_class}-background-color has-background\" style=\"padding-top:32px;padding-right:32px;padding-bottom:32px;padding-left:32px\">"
         . v3_h( $c['title'], 2 ) . "\n\n" . v3_paras( array( $c['para'] ) )
         . "\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/contact\">" . v3_esc( $c['cta'] ) . "</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->"
         . "\n\n<!-- wp:paragraph {\"fontSize\":\"small\"} -->\n<p class=\"has-small-font-size\">" . v3_esc( $cta_alt ) . "</p>\n<!-- /wp:paragraph -->"
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
