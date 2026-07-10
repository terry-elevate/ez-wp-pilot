<?php
// Section renderer library — Squarespace/premium-theme caliber
// Each function produces Gutenberg block markup with design-system CSS classes.

function sec_esc( $v ) { return esc_html( trim( (string) $v ) ); }

function sec_split_long( $text ) {
    if ( str_word_count( $text ) <= 80 ) { return array( $text ); }
    $sentences = preg_split( '/(?<=[.!?;])\s+/', trim( $text ) );
    $chunks = array(); $buf = '';
    foreach ( $sentences as $s ) {
        $test = $buf ? $buf . ' ' . $s : $s;
        if ( str_word_count( $test ) > 70 && $buf !== '' ) { $chunks[] = $buf; $buf = $s; }
        else { $buf = $test; }
    }
    if ( $buf ) { $chunks[] = $buf; }
    return $chunks;
}

function sec_p( $text, $class = '' ) {
    $chunks = sec_split_long( $text );
    $out = array();
    $ca = $class ? " {\"className\":\"{$class}\"}" : '';
    $cc = $class ? " class=\"{$class}\"" : '';
    foreach ( $chunks as $c ) {
        $out[] = "<!-- wp:paragraph{$ca} -->\n<p{$cc}>" . sec_esc( $c ) . "</p>\n<!-- /wp:paragraph -->";
    }
    return implode( "\n\n", $out );
}

function sec_h( $text, $level = 2, $class = '' ) {
    $ca = "\"level\":{$level}";
    if ( $class ) { $ca .= ",\"className\":\"{$class}\""; }
    $cc = $class ? " class=\"wp-element-heading {$class}\"" : '';
    return "<!-- wp:heading {{$ca}} -->\n<h{$level}{$cc}>" . sec_esc( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

function sec_list( $items, $class = '' ) {
    $li = '';
    foreach ( $items as $it ) {
        foreach ( sec_split_long( $it ) as $c ) {
            $li .= "<!-- wp:list-item -->\n<li>" . sec_esc( $c ) . "</li>\n<!-- /wp:list-item -->\n";
        }
    }
    $ca = $class ? " {\"className\":\"{$class}\"}" : '';
    $cc = $class ? " {$class}" : '';
    return "<!-- wp:list{$ca} -->\n<ul class=\"wp-block-list{$cc}\">{$li}</ul>\n<!-- /wp:list -->";
}

function sec_button( $text, $href = '/contact/', $outline = false ) {
    $cls = $outline ? ' is-style-outline' : '';
    $ca = $outline ? ' {"className":"is-style-outline"}' : '';
    return "<!-- wp:button{$ca} -->\n<div class=\"wp-block-button{$cls}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $href ) . "\">" . sec_esc( $text ) . "</a></div>\n<!-- /wp:button -->";
}

function sec_buttons( $buttons_html ) {
    return "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n<div class=\"wp-block-buttons\">{$buttons_html}</div>\n<!-- /wp:buttons -->";
}

function sec_img_tag( $img ) {
    if ( ! $img ) { return ''; }
    return "<img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/>";
}

function sec_img_block( $img, $align = '' ) {
    if ( ! $img ) { return ''; }
    $a = $align ? ",\"align\":\"{$align}\"" : '';
    $ac = $align ? " align{$align}" : '';
    return "<!-- wp:image {\"id\":{$img['id']},\"sizeSlug\":\"large\",\"linkDestination\":\"none\"{$a}} -->\n"
         . "<figure class=\"wp-block-image size-large{$ac}\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/></figure>\n<!-- /wp:image -->";
}

// --- Band wrapper ---
function sec_band( $inner, $color = '', $extra_class = '' ) {
    $cls = 's-band';
    $bg = '';
    if ( $color === 'sand' ) { $cls .= ' s-band--sand'; $bg = 'tint'; }
    elseif ( $color === 'cream' ) { $cls .= ' s-band--cream'; }
    elseif ( $color === 'ink' ) { $cls .= ' s-band--ink'; $bg = 'contrast'; }
    elseif ( $color === 'ember' ) { $cls .= ' s-band--ember'; $bg = 'accent'; }
    elseif ( $color === 'gradient' ) { $cls .= ' s-band--gradient'; $bg = 'contrast'; }
    elseif ( $color === 'warm-gradient' ) { $cls .= ' s-band--warm-gradient'; }
    elseif ( $color === 'dark-gradient' ) { $cls .= ' s-band--dark-gradient'; $bg = 'contrast'; }
    elseif ( $color === 'white' ) { $cls .= ' s-band--white'; }
    if ( $extra_class ) { $cls .= " {$extra_class}"; }
    $bgAttr = $bg ? ",\"backgroundColor\":\"{$bg}\"" : '';
    $bgCls = $bg ? " has-{$bg}-background-color has-background" : '';
    return "<!-- wp:group {\"align\":\"full\",\"className\":\"{$cls}\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"6rem\",\"bottom\":\"6rem\",\"left\":\"2.5rem\",\"right\":\"2.5rem\"}}}{$bgAttr},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1200px\"}} -->\n"
         . "<div class=\"wp-block-group alignfull {$cls}{$bgCls}\" style=\"padding-top:6rem;padding-right:2.5rem;padding-bottom:6rem;padding-left:2.5rem\">\n{$inner}\n</div>\n<!-- /wp:group -->";
}

function sec_wrap( $inner, $spacing = '4.5rem' ) {
    return "<!-- wp:group {\"style\":{\"spacing\":{\"padding\":{\"top\":\"{$spacing}\",\"bottom\":\"{$spacing}\",\"left\":\"2.5rem\",\"right\":\"2.5rem\"}}},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1200px\"}} -->\n"
         . "<div class=\"wp-block-group\" style=\"padding-top:{$spacing};padding-right:2.5rem;padding-bottom:{$spacing};padding-left:2.5rem\">\n{$inner}\n</div>\n<!-- /wp:group -->";
}

// ============================================================================
// HERO SECTIONS
// ============================================================================

function sec_hero_cover( $img, $headline, $subline, $cta_text ) {
    if ( ! $img ) { return sec_hero_text( $headline, $subline, $cta_text ); }
    $inner = "<!-- wp:paragraph {\"align\":\"center\",\"className\":\"s-lead\",\"textColor\":\"base\",\"fontSize\":\"medium\"} -->\n<p class=\"has-text-align-center s-lead has-base-color has-text-color has-medium-font-size\">" . sec_esc( $subline ) . "</p>\n<!-- /wp:paragraph -->\n\n"
           . "<!-- wp:heading {\"textAlign\":\"center\",\"level\":2,\"textColor\":\"base\",\"className\":\"s-hero-cover\"} -->\n<h2 class=\"wp-element-heading has-text-align-center has-base-color has-text-color s-hero-cover\">" . sec_esc( $headline ) . "</h2>\n<!-- /wp:heading -->\n\n"
           . sec_buttons( sec_button( $cta_text ) . sec_button( 'Learn More', '#content', true ) );
    return "<!-- wp:cover {\"url\":\"" . esc_url( $img['url'] ) . "\",\"id\":{$img['id']},\"dimRatio\":70,\"overlayColor\":\"contrast\",\"isUserOverlayColor\":true,\"minHeight\":85,\"minHeightUnit\":\"vh\",\"align\":\"full\",\"className\":\"s-hero-cover\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"8rem\",\"bottom\":\"8rem\",\"left\":\"2rem\",\"right\":\"2rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
         . "<div class=\"wp-block-cover alignfull s-hero-cover\" style=\"min-height:85vh;padding-top:8rem;padding-right:2rem;padding-bottom:8rem;padding-left:2rem\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim\"></span>"
         . "<img class=\"wp-block-cover__image-background wp-image-{$img['id']}\" alt=\"" . esc_attr( $img['alt'] ) . "\" src=\"" . esc_url( $img['url'] ) . "\"/>"
         . "<div class=\"wp-block-cover__inner-container\">\n{$inner}\n</div></div>\n<!-- /wp:cover -->";
}

function sec_hero_split( $img, $headline, $intro_text, $cta_text ) {
    $text_col = sec_h( $headline, 2, 's-hero-split__text' ) . "\n\n" . sec_p( $intro_text ) . "\n\n" . sec_buttons( sec_button( $cta_text ) );
    if ( ! $img ) { return sec_band( $text_col, 'cream' ); }
    return "<!-- wp:media-text {\"mediaId\":{$img['id']},\"mediaLink\":\"#\",\"mediaType\":\"image\",\"mediaWidth\":50,\"mediaPosition\":\"right\",\"align\":\"full\",\"style\":{\"elements\":{\"link\":{\"color\":{\"text\":\"var:preset|color|contrast\"}}}}} -->\n"
         . "<div class=\"wp-block-media-text alignfull has-media-on-the-right\" style=\"grid-template-columns:auto 50%\"><div class=\"wp-block-media-text__content\">\n{$text_col}\n</div>"
         . "<figure class=\"wp-block-media-text__media\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\" style=\"object-fit:cover;height:100%\"/></figure></div>\n<!-- /wp:media-text -->";
}

function sec_hero_text( $headline, $subline, $cta_text ) {
    $inner = "<!-- wp:paragraph {\"align\":\"center\",\"className\":\"s-lead\",\"fontSize\":\"medium\"} -->\n<p class=\"has-text-align-center s-lead has-medium-font-size\">" . sec_esc( $subline ) . "</p>\n<!-- /wp:paragraph -->\n\n"
           . sec_h( $headline, 2, 'has-text-align-center' ) . "\n\n"
           . sec_buttons( sec_button( $cta_text ) . sec_button( 'Our Services', '#content', true ) );
    return sec_band( $inner, 'cream', 's-hero-text s-band--spacious' );
}

function sec_hero_offset( $img, $headline, $intro_text, $cta_text ) {
    if ( ! $img ) { return sec_hero_text( $headline, $intro_text, $cta_text ); }
    $card = sec_h( $headline, 2 ) . "\n\n" . sec_p( $intro_text ) . "\n\n" . sec_buttons( sec_button( $cta_text ) );
    $inner = "<!-- wp:group {\"className\":\"s-hero-offset__card\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"3rem\",\"bottom\":\"3rem\",\"left\":\"3rem\",\"right\":\"3rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
           . "<div class=\"wp-block-group s-hero-offset__card\" style=\"padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem\">{$card}</div>\n<!-- /wp:group -->";
    return "<!-- wp:cover {\"url\":\"" . esc_url( $img['url'] ) . "\",\"id\":{$img['id']},\"dimRatio\":40,\"overlayColor\":\"contrast\",\"isUserOverlayColor\":true,\"minHeight\":500,\"align\":\"full\",\"className\":\"s-hero-offset\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"6rem\",\"bottom\":\"6rem\",\"left\":\"3rem\",\"right\":\"3rem\"}}},\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1200px\"}} -->\n"
         . "<div class=\"wp-block-cover alignfull s-hero-offset\" style=\"min-height:500px;padding-top:6rem;padding-right:3rem;padding-bottom:6rem;padding-left:3rem\"><span aria-hidden=\"true\" class=\"wp-block-cover__background has-contrast-background-color has-background-dim-40 has-background-dim\"></span>"
         . "<img class=\"wp-block-cover__image-background wp-image-{$img['id']}\" alt=\"" . esc_attr( $img['alt'] ) . "\" src=\"" . esc_url( $img['url'] ) . "\"/>"
         . "<div class=\"wp-block-cover__inner-container\">\n{$inner}\n</div></div>\n<!-- /wp:cover -->";
}

// ============================================================================
// CONTENT SECTIONS
// ============================================================================

function sec_content_prose( $heading, $paras ) {
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    return sec_wrap( $text );
}

function sec_content_media_left( $heading, $paras, $img, $list = null ) {
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    if ( $list ) { $text .= "\n\n" . sec_list( $list ); }
    if ( ! $img ) { return sec_wrap( $text ); }
    return sec_wrap( "<!-- wp:media-text {\"mediaId\":{$img['id']},\"mediaLink\":\"#\",\"mediaType\":\"image\",\"mediaWidth\":50} -->\n"
         . "<div class=\"wp-block-media-text\" style=\"grid-template-columns:50% auto\"><figure class=\"wp-block-media-text__media\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/></figure>"
         . "<div class=\"wp-block-media-text__content\">\n{$text}\n</div></div>\n<!-- /wp:media-text -->" );
}

function sec_content_media_right( $heading, $paras, $img, $list = null ) {
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    if ( $list ) { $text .= "\n\n" . sec_list( $list ); }
    if ( ! $img ) { return sec_wrap( $text ); }
    return sec_wrap( "<!-- wp:media-text {\"mediaId\":{$img['id']},\"mediaLink\":\"#\",\"mediaType\":\"image\",\"mediaWidth\":50,\"mediaPosition\":\"right\"} -->\n"
         . "<div class=\"wp-block-media-text has-media-on-the-right\" style=\"grid-template-columns:auto 50%\"><div class=\"wp-block-media-text__content\">\n{$text}\n</div>"
         . "<figure class=\"wp-block-media-text__media\"><img src=\"" . esc_url( $img['url'] ) . "\" alt=\"" . esc_attr( $img['alt'] ) . "\" class=\"wp-image-{$img['id']}\"/></figure></div>\n<!-- /wp:media-text -->" );
}

function sec_content_wide_img( $heading, $paras, $img ) {
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    if ( $img ) { $text .= "\n\n" . sec_img_block( $img, 'wide' ); }
    return sec_wrap( $text );
}

function sec_content_indent( $heading, $paras ) {
    return sec_wrap( sec_h( $heading, 2 ) . "\n\n"
         . "<!-- wp:group {\"className\":\"s-indent\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-indent\">"
         . sec_p( implode( ' ', (array) $paras ) )
         . "</div>\n<!-- /wp:group -->" );
}

function sec_content_steps( $heading, $items ) {
    return sec_wrap( sec_h( $heading, 2 ) . "\n\n" . sec_list( $items, 's-steps' ) );
}

function sec_content_icon_list( $heading, $paras, $list ) {
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    $text .= "\n\n" . sec_list( $list, 's-icon-list' );
    return sec_wrap( $text );
}

function sec_content_timeline( $heading, $items ) {
    return sec_wrap( sec_h( $heading, 2 ) . "\n\n" . sec_list( $items, 's-timeline' ) );
}

// Overlap card — image with overlapping text card below (premium pattern)
function sec_content_overlap( $heading, $paras, $img ) {
    if ( ! $img ) { return sec_content_prose( $heading, $paras ); }
    $text = sec_h( $heading, 2 ) . "\n\n" . sec_p( implode( ' ', (array) $paras ) );
    $inner = "<!-- wp:group {\"className\":\"s-overlap\",\"layout\":{\"type\":\"constrained\",\"wideSize\":\"1000px\"}} -->\n<div class=\"wp-block-group s-overlap\">"
           . "<!-- wp:group {\"className\":\"s-overlap__img\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-overlap__img\">"
           . sec_img_block( $img, 'wide' )
           . "</div>\n<!-- /wp:group -->"
           . "<!-- wp:group {\"className\":\"s-overlap__card\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"2.5rem\",\"bottom\":\"2.5rem\",\"left\":\"2.5rem\",\"right\":\"2.5rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
           . "<div class=\"wp-block-group s-overlap__card\" style=\"padding-top:2.5rem;padding-right:2.5rem;padding-bottom:2.5rem;padding-left:2.5rem\">{$text}</div>\n<!-- /wp:group -->"
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

// ============================================================================
// STAT COUNTERS — premium pattern with giant numbers
// ============================================================================

function sec_stats( $items ) {
    // items: array of ['number' => '30°F', 'label' => 'Temperature drop']
    $grid = '';
    foreach ( $items as $item ) {
        $grid .= "<!-- wp:column {\"className\":\"s-stats__item\"} -->\n<div class=\"wp-block-column s-stats__item\">"
               . "<!-- wp:paragraph {\"className\":\"s-stats__number\",\"fontSize\":\"x-large\"} -->\n<p class=\"s-stats__number has-x-large-font-size\">" . sec_esc( $item['number'] ) . "</p>\n<!-- /wp:paragraph -->"
               . "<!-- wp:paragraph {\"className\":\"s-stats__label\"} -->\n<p class=\"s-stats__label\">" . sec_esc( $item['label'] ) . "</p>\n<!-- /wp:paragraph -->"
               . "</div>\n<!-- /wp:column -->\n";
    }
    $inner = "<!-- wp:columns {\"align\":\"wide\",\"className\":\"s-stats\"} -->\n<div class=\"wp-block-columns alignwide s-stats\">{$grid}</div>\n<!-- /wp:columns -->";
    return sec_wrap( $inner );
}

// ============================================================================
// CARD SECTIONS
// ============================================================================

function sec_cards( $heading, $cards, $cols = 3, $variant = '' ) {
    $grid_class = $cols === 2 ? 's-cards-2' : 's-cards-3';
    $card_class = 's-card';
    if ( $variant === 'accent' ) { $card_class .= ' s-card--accent'; }
    if ( $variant === 'glass' ) { $card_class .= ' s-card--glass'; }

    $cols_html = '';
    foreach ( $cards as $card ) {
        $inner = '';
        if ( ! empty( $card['title'] ) ) { $inner .= sec_h( $card['title'], 3 ); }
        if ( ! empty( $card['text'] ) ) { $inner .= "\n\n" . sec_p( $card['text'] ); }
        if ( ! empty( $card['list'] ) ) { $inner .= "\n\n" . sec_list( $card['list'] ); }
        $cols_html .= "<!-- wp:column {\"className\":\"{$card_class}\"} -->\n<div class=\"wp-block-column {$card_class}\">{$inner}</div>\n<!-- /wp:column -->\n\n";
    }
    $inner = sec_h( $heading, 2, 'has-text-align-center' ) . "\n\n"
           . "<!-- wp:columns {\"align\":\"wide\",\"className\":\"{$grid_class}\"} -->\n<div class=\"wp-block-columns alignwide {$grid_class}\">\n{$cols_html}</div>\n<!-- /wp:columns -->";
    return sec_wrap( $inner );
}

// Feature row — icon-centric cards (premium pattern)
function sec_feature_row( $heading, $features ) {
    $cols_html = '';
    foreach ( $features as $f ) {
        $inner = "<!-- wp:group {\"className\":\"s-card__icon\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-card__icon\">●</div>\n<!-- /wp:group -->\n"
               . sec_h( $f['title'] ?? $f, 3 ) . "\n\n"
               . ( ! empty( $f['text'] ) ? sec_p( $f['text'] ) : '' );
        $cols_html .= "<!-- wp:column {\"className\":\"s-card\"} -->\n<div class=\"wp-block-column s-card\">{$inner}</div>\n<!-- /wp:column -->\n";
    }
    $inner = sec_h( $heading, 2, 'has-text-align-center' ) . "\n\n"
           . "<!-- wp:columns {\"align\":\"wide\",\"className\":\"s-feature-row\"} -->\n<div class=\"wp-block-columns alignwide s-feature-row\">{$cols_html}</div>\n<!-- /wp:columns -->";
    return sec_wrap( $inner );
}

// ============================================================================
// TABLE SECTION
// ============================================================================

function sec_table( $heading, $headers, $rows, $variant = '' ) {
    $cls = 's-table';
    if ( $variant ) { $cls .= " s-table--{$variant}"; }
    $thead = '<thead><tr>';
    foreach ( $headers as $h ) { $thead .= '<th>' . sec_esc( $h ) . '</th>'; }
    $thead .= '</tr></thead>';
    $tbody = '<tbody>';
    foreach ( $rows as $row ) {
        $tbody .= '<tr>';
        foreach ( $row as $cell ) { $tbody .= '<td>' . sec_esc( $cell ) . '</td>'; }
        $tbody .= '</tr>';
    }
    $tbody .= '</tbody>';
    $inner = sec_h( $heading, 2, 'has-text-align-center' ) . "\n\n"
           . "<!-- wp:group {\"className\":\"{$cls}\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group {$cls}\">"
           . "<!-- wp:table {\"hasFixedLayout\":true} -->\n<figure class=\"wp-block-table\"><table class=\"has-fixed-layout\">{$thead}{$tbody}</table></figure>\n<!-- /wp:table -->"
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

// ============================================================================
// QUOTE SECTIONS
// ============================================================================

function sec_quote( $text, $cite = '', $variant = '' ) {
    $cls = 's-quote';
    if ( $variant ) { $cls .= " s-quote--{$variant}"; }
    $cite_html = $cite ? "<cite>" . sec_esc( $cite ) . "</cite>" : '';
    return sec_wrap( "<!-- wp:group {\"className\":\"{$cls}\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group {$cls}\">"
         . "<!-- wp:pullquote -->\n<figure class=\"wp-block-pullquote\"><blockquote><p>" . sec_esc( $text ) . "</p>{$cite_html}</blockquote></figure>\n<!-- /wp:pullquote -->"
         . "</div>\n<!-- /wp:group -->" );
}

// ============================================================================
// CTA SECTIONS
// ============================================================================

function sec_cta_center( $heading, $text, $button_text, $band = 'sand' ) {
    $inner = "<!-- wp:group {\"className\":\"s-cta\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-cta\">"
           . sec_h( $heading, 2 ) . "\n\n" . sec_p( $text ) . "\n\n" . sec_buttons( sec_button( $button_text ) )
           . "</div>\n<!-- /wp:group -->";
    return sec_band( $inner, $band );
}

function sec_cta_inline( $text, $link_text ) {
    return sec_wrap( "<!-- wp:group {\"className\":\"s-cta--inline\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"1.5rem\",\"bottom\":\"1.5rem\",\"left\":\"2rem\",\"right\":\"2rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
         . "<div class=\"wp-block-group s-cta--inline\" style=\"padding-top:1.5rem;padding-right:2rem;padding-bottom:1.5rem;padding-left:2rem\">"
         . "<!-- wp:paragraph -->\n<p>" . sec_esc( $text ) . " <a href=\"/contact/\">" . sec_esc( $link_text ) . "</a>.</p>\n<!-- /wp:paragraph -->"
         . "</div>\n<!-- /wp:group -->", '2rem' );
}

function sec_cta_card( $heading, $text, $button_text ) {
    $inner = "<!-- wp:group {\"className\":\"s-cta--card\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"4rem\",\"bottom\":\"4rem\",\"left\":\"3.5rem\",\"right\":\"3.5rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
           . "<div class=\"wp-block-group s-cta--card\" style=\"padding-top:4rem;padding-right:3.5rem;padding-bottom:4rem;padding-left:3.5rem\">"
           . sec_h( $heading, 2 ) . "\n\n" . sec_p( $text ) . "\n\n" . sec_buttons( sec_button( $button_text ) )
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

// Full-bleed CTA with gradient (premium pattern)
function sec_cta_fullbleed( $heading, $text, $button_text ) {
    $inner = "<!-- wp:group {\"className\":\"s-cta--fullbleed\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-cta--fullbleed\">"
           . sec_h( $heading, 2 ) . "\n\n" . sec_p( $text ) . "\n\n" . sec_buttons( sec_button( $button_text ) . sec_button( 'Call Now', 'tel:+1', true ) )
           . "</div>\n<!-- /wp:group -->";
    return sec_band( $inner, 'dark-gradient', 's-band--spacious' );
}

// ============================================================================
// FAQ SECTIONS
// ============================================================================

function sec_faq( $heading, $items, $variant = '' ) {
    $cls = 's-accordion';
    if ( $variant ) { $cls .= " s-accordion--{$variant}"; }
    $details = '';
    foreach ( $items as $qa ) {
        $details .= "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . sec_esc( $qa['q'] ) . "</summary>\n"
                  . sec_p( $qa['a'] ) . "\n</details>\n<!-- /wp:details -->\n\n";
    }
    $inner = sec_h( $heading, 2, 'has-text-align-center' ) . "\n\n"
           . "<!-- wp:group {\"className\":\"{$cls}\",\"layout\":{\"type\":\"constrained\",\"wideSize\":\"720px\"}} -->\n<div class=\"wp-block-group {$cls}\">"
           . $details
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

// ============================================================================
// DIAGNOSTIC / WARNING SECTIONS
// ============================================================================

function sec_diagnostic( $title, $items ) {
    $inner = "<!-- wp:group {\"className\":\"s-diagnostic\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"3rem\",\"bottom\":\"3rem\",\"left\":\"3rem\",\"right\":\"3rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
           . "<div class=\"wp-block-group s-diagnostic\" style=\"padding-top:3rem;padding-right:3rem;padding-bottom:3rem;padding-left:3rem\">"
           . sec_h( $title, 3 ) . "\n\n" . sec_list( $items )
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

function sec_warning_box( $title, $items ) {
    $inner = "<!-- wp:group {\"className\":\"s-warning\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"2.5rem\",\"bottom\":\"2.5rem\",\"left\":\"2.5rem\",\"right\":\"2.5rem\"}}},\"layout\":{\"type\":\"constrained\"}} -->\n"
           . "<div class=\"wp-block-group s-warning\" style=\"padding-top:2.5rem;padding-right:2.5rem;padding-bottom:2.5rem;padding-left:2.5rem\">"
           . sec_h( $title, 3 ) . "\n\n" . sec_list( $items )
           . "</div>\n<!-- /wp:group -->";
    return sec_wrap( $inner );
}

// ============================================================================
// PILLS
// ============================================================================

function sec_pills( $items, $variant = '' ) {
    $cls = 's-pills';
    if ( $variant ) { $cls .= " s-pills--{$variant}"; }
    return sec_wrap( sec_list( $items, $cls ) );
}

// ============================================================================
// NEARBY
// ============================================================================

function sec_nearby( $nearby ) {
    if ( empty( $nearby ) ) { return ''; }
    $links = array();
    foreach ( (array) $nearby as $ncity ) {
        $np = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1,
            'meta_key' => '_location_city', 'meta_value' => $ncity ) );
        if ( $np ) {
            $links[] = '<a href="' . esc_url( get_permalink( $np[0]->ID ) ) . '">' . esc_html( str_replace( ', PA', '', $ncity ) ) . '</a>';
        }
    }
    if ( ! $links ) { return ''; }
    return sec_wrap( "<!-- wp:group {\"className\":\"s-nearby\",\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group s-nearby\">"
         . "<!-- wp:paragraph -->\n<p><strong>Also serving nearby:</strong> " . implode( ' · ', $links ) . "</p>\n<!-- /wp:paragraph -->"
         . "</div>\n<!-- /wp:group -->" );
}

// ============================================================================
// SEPARATOR
// ============================================================================

function sec_sep( $variant = '' ) {
    $cls = 's-sep';
    if ( $variant ) { $cls .= " s-sep--{$variant}"; }
    return "<!-- wp:separator {\"className\":\"{$cls}\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity {$cls}\"/>\n<!-- /wp:separator -->";
}
