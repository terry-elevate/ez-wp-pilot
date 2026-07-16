<?php
// v3: location pages as modern landing pages with full-bleed sections and visual hierarchy.
// Usage: wp eval-file v3-assemble.php /path/to/v3-content-N.json
$file = isset( $args[0] ) ? $args[0] : '';
if ( ! $file || ! file_exists( $file ) ) { echo "Content file not found: $file\n"; exit; }
$entries = json_decode( file_get_contents( $file ), true );
if ( ! is_array( $entries ) ) { echo 'Bad JSON: ' . json_last_error_msg() . "\n"; exit; }

$pool = get_option( 'ez_img_pool', array() );
$img_use_counter = array();

function v3_esc( $v ) { return esc_html( trim( (string) $v ) ); }

function v3_pick_image( $topic, &$counter, $pool ) {
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
    $i = $counter[ $topic ] ?? 0;
    $counter[ $topic ] = $i + 1;
    $id = $pool[ $topic ][ $i % count( $pool[ $topic ] ) ];
    $src = wp_get_attachment_image_src( $id, 'large' );
    if ( ! $src ) { return null; }
    return array( 'id' => $id, 'url' => $src[0],
        'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ),
        'cred' => get_post_meta( $id, '_ez_attribution', true ) );
}

function v3_split_long( $text ) {
    if ( str_word_count( $text ) <= 80 ) { return array( $text ); }
    $sentences = preg_split( '/(?<=[.!?;])\s+/', trim( $text ) );
    $chunks = array(); $buf = '';
    foreach ( $sentences as $s ) {
        $test = $buf ? $buf . ' ' . $s : $s;
        if ( str_word_count( $test ) > 70 && $buf !== '' ) { $chunks[] = $buf; $buf = $s; }
        else { $buf = $test; }
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

// ============================================================================
// BLOCK PRIMITIVES — modern landing page building blocks
// ============================================================================

function b_p( $text, $class = '', $size = '' ) {
    $attrs = array();
    if ( $class ) { $attrs[] = "\"className\":\"{$class}\""; }
    if ( $size ) { $attrs[] = "\"fontSize\":\"{$size}\""; }
    $json = $attrs ? ' {' . implode( ',', $attrs ) . '}' : '';
    $cls = $class ? " class=\"{$class}" . ( $size ? " has-{$size}-font-size\"" : '"' ) : ( $size ? " class=\"has-{$size}-font-size\"" : '' );
    $chunks = v3_split_long( $text );
    $out = array();
    foreach ( $chunks as $chunk ) {
        $out[] = "<!-- wp:paragraph{$json} -->\n<p{$cls}>" . v3_esc( $chunk ) . "</p>\n<!-- /wp:paragraph -->";
    }
    return implode( "\n\n", $out );
}

function b_h( $text, $level = 2, $align = '', $color = '' ) {
    $attrs = array( "\"level\":{$level}" );
    $cls = array();
    if ( $align ) { $attrs[] = "\"textAlign\":\"{$align}\""; $cls[] = "has-text-align-{$align}"; }
    if ( $color ) { $attrs[] = "\"textColor\":\"{$color}\""; $cls[] = "has-{$color}-color has-text-color"; }
    $clsStr = $cls ? ' class="wp-element-heading ' . implode( ' ', $cls ) . '"' : '';
    return "<!-- wp:heading {" . implode( ',', $attrs ) . "} -->\n<h{$level}{$clsStr}>" . v3_esc( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

function b_img( $img, $align = '' ) {
    if ( ! $img ) { return ''; }
    $a = $align ? ",\"align\":\"{$align}\"" : '';
    $ac = $align ? " align{$align}" : '';
    return "<!-- wp:image {\"id\":{$img['id']},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"{$a}} -->\n"
         . "<figure class=\"wp-block-image size-large{$ac}\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/></figure>\n<!-- /wp:image -->";
}

function b_list( $items, $class = '' ) {
    $li = '';
    foreach ( $items as $it ) {
        foreach ( v3_split_long( $it ) as $chunk ) {
            $li .= "<!-- wp:list-item -->\n<li>" . v3_esc( $chunk ) . "</li>\n<!-- /wp:list-item -->\n\n";
        }
    }
    $ca = $class ? " {\"className\":\"{$class}\"}" : '';
    $cc = $class ? " {$class}" : '';
    return "<!-- wp:list{$ca} -->\n<ul class=\"wp-block-list{$cc}\">" . rtrim( $li ) . "</ul>\n<!-- /wp:list -->";
}

function b_button( $text, $href = '/contact/', $outline = false ) {
    if ( $outline ) {
        return "<!-- wp:button {\"className\":\"is-style-outline\"} -->\n<div class=\"wp-block-button is-style-outline\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $href ) . "\">" . v3_esc( $text ) . "</a></div>\n<!-- /wp:button -->";
    }
    return "<!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $href ) . "\">" . v3_esc( $text ) . "</a></div>\n<!-- /wp:button -->";
}

function b_buttons( $inner, $center = true ) {
    $layout = $center ? ' {"layout":{"type":"flex","justifyContent":"center"}}' : '';
    return "<!-- wp:buttons{$layout} -->\n<div class=\"wp-block-buttons\">{$inner}</div>\n<!-- /wp:buttons -->";
}

// Full-width band with background
function b_band( $inner, $bg = 'tint', $extra_class = '' ) {
    $cls = "loc-band";
    if ( $bg === 'dark' ) { $cls .= " loc-band--dark"; }
    elseif ( $bg === 'accent' ) { $cls .= " loc-band--accent"; }
    else { $cls .= " loc-band--tint"; }
    if ( $extra_class ) { $cls .= " {$extra_class}"; }
    $bgColor = $bg === 'dark' ? 'contrast' : ( $bg === 'accent' ? 'accent' : 'tint' );
    return "<!-- wp:group {\"align\":\"full\",\"className\":\"{$cls}\",\"backgroundColor\":\"{$bgColor}\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"4rem\",\"bottom\":\"4rem\",\"left\":\"2rem\",\"right\":\"2rem\"}}},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1120px\"}} -->\n"
         . "<div class=\"wp-block-group alignfull {$cls} has-{$bgColor}-background-color has-background\" style=\"padding-top:4rem;padding-right:2rem;padding-bottom:4rem;padding-left:2rem\">\n{$inner}\n</div>\n<!-- /wp:group -->";
}

// Constrained content section (no bg)
function b_section( $inner, $spacing = '3rem' ) {
    return "<!-- wp:group {\"style\":{\"spacing\":{\"padding\":{\"top\":\"{$spacing}\",\"bottom\":\"{$spacing}\",\"left\":\"2rem\",\"right\":\"2rem\"}}},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1120px\"}} -->\n"
         . "<div class=\"wp-block-group\" style=\"padding-top:{$spacing};padding-right:2rem;padding-bottom:{$spacing};padding-left:2rem\">\n{$inner}\n</div>\n<!-- /wp:group -->";
}

// Key facts as pill-style highlights
function b_stats( $facts ) {
    return b_list( $facts, 'key-facts' );
}

// Feature cards as columns
function b_cards( $items ) {
    $cols = '';
    foreach ( $items as $item ) {
        $inner = '';
        if ( isset( $item['title'] ) ) { $inner .= b_h( $item['title'], 3 ); }
        if ( isset( $item['text'] ) ) { $inner .= "\n\n" . b_p( $item['text'] ); }
        if ( isset( $item['list'] ) ) { $inner .= "\n\n" . b_list( $item['list'] ); }
        $cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$inner}</div>\n<!-- /wp:column -->\n\n";
    }
    return "<!-- wp:columns {\"align\":\"wide\",\"className\":\"loc-features\"} -->\n<div class=\"wp-block-columns alignwide loc-features\">\n{$cols}</div>\n<!-- /wp:columns -->";
}

// Media + text (side by side)
function b_media_text( $img, $text_blocks, $media_right = false ) {
    if ( ! $img ) { return $text_blocks; }
    $posattr = $media_right ? ',\"mediaPosition\":\"right\"' : '';
    $posclass = $media_right ? ' has-media-on-the-right' : '';
    $grid = $media_right ? 'grid-template-columns:auto 45%' : 'grid-template-columns:45% auto';
    return "<!-- wp:media-text {\"mediaId\":{$img['id']},\"mediaLink\":\"#\",\"mediaType\":\"image\",\"mediaWidth\":45{$posattr}} -->\n"
         . "<div class=\"wp-block-media-text{$posclass}\" style=\"{$grid}\"><div class=\"wp-block-media-text__content\">\n{$text_blocks}\n</div>"
         . "<figure class=\"wp-block-media-text__media\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/></figure></div>\n<!-- /wp:media-text -->";
}

function b_cover( $img, $inner, $min_height = 350, $dim = 60 ) {
    if ( ! $img ) { return $inner; }
    return "<!-- wp:cover {\"url\":\"" . esc_url( $img['url'] ) . "\",\"id\":{$img['id']},\"dimRatio\":{$dim},\"overlayColor\":\"contrast\",\"isUserOverlayColor\":true,\"minHeight\":{$min_height},\"style\":{\"border\":{\"radius\":\"16px\"}}} -->\n"
         . "<div class=\"wp-block-cover\" style=\"border-radius:16px;min-height:{$min_height}px\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-contrast-background-color has-background-dim-{$dim} has-background-dim\"></span>"
         . "<img class=\"wp-block-cover__image-background wp-image-{$img['id']}\" alt=\"" . esc_attr( $img['alt'] ) . "\" src=\"" . esc_url( $img['url'] ) . "\"/>"
         . "<div class=\"wp-block-cover__inner-container\">\n{$inner}\n</div></div>\n<!-- /wp:cover -->";
}

function b_pullquote( $text, $cite = '' ) {
    $c = $cite ? "<cite>" . v3_esc( $cite ) . "</cite>" : '';
    return "<!-- wp:pullquote -->\n<figure class=\"wp-block-pullquote\"><blockquote><p>" . v3_esc( $text ) . "</p>{$c}</blockquote></figure>\n<!-- /wp:pullquote -->";
}

function b_details( $summary, $inner ) {
    return "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . v3_esc( $summary ) . "</summary>\n{$inner}\n</details>\n<!-- /wp:details -->";
}

function b_table( $headers, $rows ) {
    $thead = '<thead><tr>';
    foreach ( $headers as $h ) { $thead .= '<th>' . v3_esc( $h ) . '</th>'; }
    $thead .= '</tr></thead>';
    $tbody = '<tbody>';
    foreach ( $rows as $row ) {
        $tbody .= '<tr>';
        foreach ( $row as $cell ) { $tbody .= '<td>' . v3_esc( $cell ) . '</td>'; }
        $tbody .= '</tr>';
    }
    $tbody .= '</tbody>';
    return "<!-- wp:table {\"hasFixedLayout\":true} -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">{$thead}{$tbody}</table></figure>\n<!-- /wp:table -->";
}

function b_separator() {
    return "<!-- wp:separator {\"align\":\"center\",\"className\":\"is-style-wide\"} -->\n<hr class=\"wp-block-separator aligncenter has-alpha-channel-opacity is-style-wide\"/>\n<!-- /wp:separator -->";
}

function b_diagnostic( $title, $items ) {
    $inner = b_h( $title, 3 ) . "\n\n" . b_list( $items );
    return "<!-- wp:group {\"className\":\"loc-diagnostic\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"2.5rem\",\"bottom\":\"2.5rem\",\"left\":\"2.5rem\",\"right\":\"2.5rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
         . "<div class=\"wp-block-group loc-diagnostic\" style=\"padding-top:2.5rem;padding-right:2.5rem;padding-bottom:2.5rem;padding-left:2.5rem\">{$inner}</div>\n<!-- /wp:group -->";
}

function b_nearby( $nearby ) {
    if ( empty( $nearby ) ) { return ''; }
    $nlinks = array();
    foreach ( (array) $nearby as $ncity ) {
        $np = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
            'meta_key' => '_location_city', 'meta_value' => $ncity ) );
        if ( $np ) {
            $nlinks[] = '<a href="' . esc_url( get_permalink( $np[0]->ID ) ) . '">' . esc_html( str_replace( ', PA', '', $ncity ) ) . '</a>';
        }
    }
    if ( ! $nlinks ) { return ''; }
    return b_p( '' ) . "\n<!-- wp:paragraph -->\n<p><strong>Serving nearby areas:</strong> " . implode( ' · ', $nlinks ) . "</p>\n<!-- /wp:paragraph -->";
}

// ============================================================================
// LAYOUT ENGINES — each produces a DIFFERENT page structure
// ============================================================================

function build_page( $en, &$img_use_counter, $pool ) {
    $layout_type = $en['layout_type'] ?? 'story-flow';
    $credits = array();
    $hero = v3_pick_image( $en['hero_topic'], $img_use_counter, $pool );
    if ( $hero && $hero['cred'] ) { $credits[] = $hero['cred']; }

    $fn = 'layout_' . str_replace( '-', '_', $layout_type );
    if ( ! function_exists( $fn ) ) { $fn = 'layout_story_flow'; }
    $blocks = $fn( $en, $hero, $credits, $img_use_counter, $pool );

    // Photo credits at very end
    if ( $credits ) {
        $blocks .= "\n\n<!-- wp:paragraph {\"fontSize\":\"small\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"2rem\"}}}} -->\n<p class=\"has-small-font-size\">Photos: " . v3_esc( implode( ' · ', array_unique( $credits ) ) ) . "</p>\n<!-- /wp:paragraph -->";
    }

    return array( $blocks, $credits, $hero );
}

// --- 1. HERO-LED: Full-width cover opener → stat cards → media-text sections ---
function layout_hero_led( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    // Hero cover with hook text
    $cover_inner = b_h( $en['hook'], 2, 'center', 'base' ) . "\n\n"
                 . "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"base\"} -->\n<p class=\"has-text-align-center has-base-color has-text-color\">" . v3_esc( $en['intro'][0] ?? '' ) . "</p>\n<!-- /wp:paragraph -->\n\n"
                 . b_buttons( b_button( $en['closing']['cta'] ?? 'Get an Assessment' ) . b_button( 'Learn More', '#content', true ), true );
    $b[] = b_cover( $hero, $cover_inner, 420, 65 );

    // Stats band
    if ( ! empty( $en['key_facts'] ) ) {
        $b[] = b_band( b_stats( array_slice( $en['key_facts'], 0, 4 ) ), 'tint' );
    }

    // Intro text section
    if ( count( $en['intro'] ) > 1 ) {
        $intro_text = '';
        foreach ( array_slice( $en['intro'], 1 ) as $p ) { $intro_text .= b_p( $p ) . "\n\n"; }
        $b[] = b_section( $intro_text );
    }

    // Sections as alternating media-text
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img && $sec_i <= 3 ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } else {
            $bg = $sec_i % 2 === 0 ? 'tint' : '';
            $inner = $text;
            if ( $bg ) { $b[] = b_band( $inner, 'tint' ); }
            else { $b[] = b_section( $inner ); }
        }

        if ( $sec_i === 2 ) {
            $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );
        }
    }

    // Local knowledge band
    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    // FAQ
    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 2. DIAGNOSTIC-FIRST: Leads with the problem/diagnostic, earns trust ---
function layout_diagnostic_first( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    // Hook as large intro
    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( implode( ' ', $en['intro'] ) ) );

    // Diagnostic card (the star of this layout)
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_section( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ) );
    }

    // Key facts as stat cards
    if ( ! empty( $en['key_facts'] ) ) {
        $b[] = b_band( b_stats( array_slice( $en['key_facts'], 0, 4 ) ), 'tint' );
    }

    // Mid CTA
    $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );

    // Sections — first as media-text, rest with h2 + alternating bands
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( ! $img ) { $img = v3_pick_image( $en['hero_topic'], $img_use_counter, $pool ); }
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img && $sec_i <= 3 ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } elseif ( $sec_i % 2 === 0 ) {
            $b[] = b_band( $text, 'tint' );
        } else {
            $b[] = b_section( $text );
        }
    }

    // Local
    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'tint' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 3. COMPARISON: Table-driven, specs prominent ---
function layout_comparison( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( implode( ' ', $en['intro'] ) ) );

    // Key facts as comparison table (hero of this layout)
    if ( ! empty( $en['key_facts'] ) ) {
        $rows = array();
        foreach ( $en['key_facts'] as $fact ) {
            $parts = preg_split( '/\s*[:\x{2014}\x{2013}—–]\s*/u', $fact, 2 );
            $rows[] = count( $parts ) === 2 ? $parts : array( $fact, '—' );
        }
        $b[] = b_section( b_table( array( 'Feature', 'Specification' ), $rows ) );
    }

    // Image
    if ( $hero ) {
        $b[] = b_section( b_img( $hero, 'wide' ) );
    }

    // Mid CTA
    $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );

    // Sections as alternating bands
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } elseif ( $sec_i % 3 === 0 ) {
            $b[] = b_band( $text, 'tint' );
        } else {
            $b[] = b_section( $text );
        }
    }

    // Quick check
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_band( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ), 'tint' );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 4. STORY-FLOW: Editorial, pullquotes, rhythm ---
function layout_story_flow( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    // Editorial opening
    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( $en['intro'][0] ?? '' ) );

    // Pullquote from key facts
    if ( ! empty( $en['key_facts'][0] ) ) {
        $b[] = b_section( b_pullquote( $en['key_facts'][0] ) );
    }

    // Rest of intro
    if ( count( $en['intro'] ) > 1 ) {
        $b[] = b_section( b_p( implode( ' ', array_slice( $en['intro'], 1 ) ) ) );
    }

    // Sections with pullquotes woven in
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } else {
            $b[] = b_section( $text );
        }

        // Interleave pullquote after section 2
        if ( $sec_i === 2 && ! empty( $en['key_facts'][1] ) ) {
            $b[] = b_band( b_pullquote( $en['key_facts'][1] ), 'tint' );
        }
        if ( $sec_i === 3 ) {
            $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );
        }
    }

    // Quick check as a styled box
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_section( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ) );
    }

    // Remaining facts as pills
    if ( ! empty( $en['key_facts'] ) && count( $en['key_facts'] ) > 2 ) {
        $b[] = b_section( b_list( array_slice( $en['key_facts'], 2 ), 'key-facts' ) );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'tint' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 5. ACCORDION: Interactive, collapsible sections ---
function layout_accordion( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( implode( ' ', $en['intro'] ) ) );

    // Stats
    if ( ! empty( $en['key_facts'] ) ) {
        $b[] = b_band( b_stats( array_slice( $en['key_facts'], 0, 4 ) ), 'tint' );
    }

    // Mid CTA early (before the interactive part)
    $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );

    // ALL sections as styled accordions
    $accordions = '';
    foreach ( $en['sections'] as $s ) {
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }
        $inner = b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $inner .= "\n\n" . b_list( $s['list'] ); }
        if ( $img ) { $inner .= "\n\n" . b_img( $img ); }
        $accordions .= b_h( $s['title'], 2 ) . "\n\n" . b_details( 'Click to expand', $inner ) . "\n\n";
    }
    $b[] = b_section( $accordions );

    // Quick check
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_band( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ), 'tint' );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 6. VISUAL-SHOWCASE: Image-heavy, gallery feel ---
function layout_visual_showcase( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    // Hero media-text with hook
    $hook_text = b_h( $en['hook'], 2 ) . "\n\n" . b_p( $en['intro'][0] ?? '' ) . "\n\n"
               . b_buttons( b_button( $en['closing']['cta'] ?? 'Get an Assessment' ), false );
    $b[] = b_section( b_media_text( $hero, $hook_text, false ) );

    // Rest of intro
    if ( count( $en['intro'] ) > 1 ) {
        $b[] = b_section( b_p( implode( ' ', array_slice( $en['intro'], 1 ) ) ) );
    }

    // Each section as full media-text, alternating sides with tint bands
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        $mt = b_media_text( $img, $text, $sec_i % 2 === 0 );
        if ( $sec_i % 2 === 0 ) {
            $b[] = b_band( $mt, 'tint' );
        } else {
            $b[] = b_section( $mt );
        }

        if ( $sec_i === 2 ) {
            $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );
        }
    }

    // Stats + Quick check in a two-col layout
    if ( ! empty( $en['key_facts'] ) ) {
        $b[] = b_band( b_stats( array_slice( $en['key_facts'], 0, 4 ) ), 'tint' );
    }
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_section( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ) );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 7. DATA-DRIVEN: Numbers first, tables, metrics ---
function layout_data_driven( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( implode( ' ', $en['intro'] ) ) );

    // Key facts as big data table
    if ( ! empty( $en['key_facts'] ) ) {
        $rows = array();
        foreach ( $en['key_facts'] as $fact ) {
            $parts = preg_split( '/\s*[:\x{2014}\x{2013}—–]\s*/u', $fact, 2 );
            $rows[] = count( $parts ) === 2 ? $parts : array( $fact, '—' );
        }
        $b[] = b_band( b_h( 'Performance data', 2, 'center' ) . "\n\n" . b_table( array( 'Metric', 'Value' ), $rows ), 'tint' );
    }

    // Hero image wide
    if ( $hero ) {
        $b[] = b_section( b_img( $hero, 'wide' ) );
    }

    $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );

    // Sections standard
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } else {
            $b[] = b_section( $text );
        }
    }

    // Quick check
    if ( ! empty( $en['quick_check'] ) ) {
        $b[] = b_band( b_diagnostic( $en['quick_check']['title'], $en['quick_check']['items'] ), 'tint' );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// --- 8. PROBLEM-SOLUTION: Red problem → green solution contrast ---
function layout_problem_solution( $en, $hero, &$credits, &$img_use_counter, $pool ) {
    $b = array();

    $b[] = b_section( b_p( $en['hook'], '', 'large' ) . "\n\n" . b_p( implode( ' ', $en['intro'] ) ) );

    // THE PROBLEM — diagnostic in red-tinted band
    if ( ! empty( $en['quick_check'] ) ) {
        $qc = $en['quick_check'];
        $problem_inner = b_h( $qc['title'], 2 ) . "\n\n" . b_list( $qc['items'] );
        $b[] = "<!-- wp:group {\"align\":\"full\",\"className\":\"loc-band problem-box\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"3rem\",\"bottom\":\"3rem\",\"left\":\"2rem\",\"right\":\"2rem\"}},\"color\":{\"background\":\"#fef3f2\"},\"border\":{\"top\":{\"color\":\"#d63638\",\"width\":\"4px\"}}},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"800px\"}} -->\n"
             . "<div class=\"wp-block-group alignfull loc-band problem-box has-background\" style=\"background-color:#fef3f2;border-top:4px solid #d63638;padding-top:3rem;padding-right:2rem;padding-bottom:3rem;padding-left:2rem\">{$problem_inner}</div>\n<!-- /wp:group -->";
    }

    $b[] = b_section( b_p( $en['mid_cta'] ?? '', 'mid-cta' ) );

    // THE SOLUTIONS — sections with h2s, images, alternating bands
    $sec_i = 0;
    foreach ( $en['sections'] as $s ) {
        $sec_i++;
        $img = ! empty( $s['image_topic'] ) ? v3_pick_image( $s['image_topic'], $img_use_counter, $pool ) : null;
        if ( ! $img ) { $img = v3_pick_image( $en['hero_topic'], $img_use_counter, $pool ); }
        if ( $img && $img['cred'] ) { $credits[] = $img['cred']; }

        $text = b_h( $s['title'], 2 ) . "\n\n" . b_p( implode( ' ', $s['paras'] ) );
        if ( ! empty( $s['list'] ) ) { $text .= "\n\n" . b_list( $s['list'] ); }

        if ( $img && $sec_i <= 2 ) {
            $b[] = b_section( b_media_text( $img, $text, $sec_i % 2 === 0 ) );
        } elseif ( $sec_i % 2 === 0 ) {
            $b[] = b_band( $text, 'tint' );
        } else {
            $b[] = b_section( $text );
        }
    }

    // Stats
    if ( ! empty( $en['key_facts'] ) ) {
        $b[] = b_band( b_stats( array_slice( $en['key_facts'], 0, 4 ) ), 'tint' );
    }

    if ( ! empty( $en['local'] ) ) {
        $b[] = b_band( b_h( $en['local']['title'], 2 ) . "\n\n" . b_p( implode( ' ', (array) $en['local']['paras'] ) ), 'dark' );
    }

    $b[] = _faq_section( $en );
    $b[] = _cta_section( $en );
    $b[] = b_section( b_nearby( $en['nearby'] ?? array() ) );

    return implode( "\n\n", array_filter( $b ) );
}

// ============================================================================
// SHARED SECTIONS
// ============================================================================

function _faq_section( $en ) {
    if ( empty( $en['faq'] ) ) { return ''; }
    $inner = b_h( $en['faq_heading'] ?? 'Frequently Asked Questions', 2, 'center' ) . "\n\n";
    foreach ( $en['faq'] as $qa ) {
        $inner .= b_details( $qa['q'], b_p( $qa['a'] ) ) . "\n\n";
    }
    return b_section( $inner );
}

function _cta_section( $en ) {
    $c = $en['closing'];
    $cta_alt = $en['cta_alt'] ?? 'Or call us — we answer questions before scheduling anything.';
    $inner = b_h( $c['title'], 2, 'center' ) . "\n\n"
           . b_p( $c['para'] ) . "\n\n"
           . b_buttons( b_button( $c['cta'] ), true ) . "\n\n"
           . "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"small\"} -->\n<p class=\"has-text-align-center has-small-font-size\">" . v3_esc( $cta_alt ) . "</p>\n<!-- /wp:paragraph -->";
    return b_band( $inner, 'tint', 'cta-band' );
}

// ============================================================================
// MAIN LOOP
// ============================================================================

$updated = 0;
foreach ( $entries as $en ) {
    $existing = get_posts( array(
        'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
        'meta_key' => '_location_city', 'meta_value' => $en['city'],
    ) );
    if ( ! $existing ) { echo "NO PAGE for {$en['city']}\n"; continue; }
    $page = $existing[0];

    $layout_type = $en['layout_type'] ?? 'story-flow';
    list( $content, $credits, $hero ) = build_page( $en, $img_use_counter, $pool );

    $update = array( 'ID' => $page->ID, 'post_content' => $content );
    if ( ! empty( $en['meta_description'] ) ) { $update['post_excerpt'] = $en['meta_description']; }
    wp_update_post( $update );
    if ( ! empty( $en['meta_description'] ) ) {
        update_post_meta( $page->ID, '_yoast_wpseo_metadesc', $en['meta_description'] );
    }
    if ( $hero ) { set_post_thumbnail( $page->ID, $hero['id'] ); }
    update_post_meta( $page->ID, '_gen_version', 'v3' );
    update_post_meta( $page->ID, '_layout_type', $layout_type );

    $wc = str_word_count( wp_strip_all_tags( $content ) );
    $ic = substr_count( $content, '<img' );
    $updated++;
    echo "UPDATED [{$layout_type}] {$en['city']} (#{$page->ID}): {$wc} words, {$ic} images\n";
}
echo "\nDone: {$updated}/" . count( $entries ) . "\n";
