<?php
/**
 * Build the Service Areas and Contact landing pages for the Keystone pilot.
 * Usage: wp eval-file /var/www/html/scripts/improve-core-pages.php
 */

function ks_attrs( $attrs ) {
    return wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function ks_heading( $text, $level = 2, $class = '', $align = '' ) {
    $attrs = array( 'level' => $level );
    if ( $class ) { $attrs['className'] = $class; }
    if ( $align ) { $attrs['textAlign'] = $align; }
    $classes = trim( 'wp-block-heading ' . $class . ( $align ? " has-text-align-{$align}" : '' ) );
    return '<!-- wp:heading ' . ks_attrs( $attrs ) . " -->\n<h{$level} class=\"{$classes}\">" . esc_html( $text ) . "</h{$level}>\n<!-- /wp:heading -->";
}

function ks_paragraph( $html, $class = '', $align = '' ) {
    $attrs = array();
    if ( $class ) { $attrs['className'] = $class; }
    if ( $align ) { $attrs['align'] = $align; }
    $attr_json = $attrs ? ' ' . ks_attrs( $attrs ) : '';
    $classes = trim( 'wp-block-paragraph ' . $class . ( $align ? " has-text-align-{$align}" : '' ) );
    return "<!-- wp:paragraph{$attr_json} -->\n<p class=\"{$classes}\">{$html}</p>\n<!-- /wp:paragraph -->";
}

function ks_buttons( $buttons, $justify = 'left' ) {
    $button_html = '';
    foreach ( $buttons as $button ) {
        $outline = ! empty( $button['outline'] );
        $attrs = $outline ? ' ' . ks_attrs( array( 'className' => 'is-style-outline' ) ) : '';
        $class = $outline ? 'wp-block-button is-style-outline' : 'wp-block-button';
        $button_html .= "<!-- wp:button{$attrs} -->\n<div class=\"{$class}\"><a class=\"wp-block-button__link wp-element-button\" href=\"" . esc_url( $button['url'] ) . '">' . esc_html( $button['label'] ) . "</a></div>\n<!-- /wp:button -->\n";
    }
    $attrs = array( 'layout' => array( 'type' => 'flex', 'justifyContent' => $justify ) );
    return '<!-- wp:buttons ' . ks_attrs( $attrs ) . " -->\n<div class=\"wp-block-buttons\">{$button_html}</div>\n<!-- /wp:buttons -->";
}

function ks_group( $inner, $class = '', $options = array() ) {
    $align = $options['align'] ?? '';
    $attrs = array();
    if ( $align ) { $attrs['align'] = $align; }
    if ( $class ) { $attrs['className'] = $class; }
    if ( ! empty( $options['background'] ) ) { $attrs['backgroundColor'] = $options['background']; }
    if ( ! empty( $options['text'] ) ) { $attrs['textColor'] = $options['text']; }
    if ( ! empty( $options['padding'] ) ) {
        list( $vertical, $horizontal ) = $options['padding'];
        $attrs['style']['spacing']['padding'] = array(
            'top' => $vertical,
            'bottom' => $vertical,
            'left' => $horizontal,
            'right' => $horizontal,
        );
    }
    $attrs['layout'] = $options['layout'] ?? array( 'type' => 'constrained', 'wideSize' => $options['wide'] ?? '1120px' );

    $classes = array( 'wp-block-group' );
    if ( $align ) { $classes[] = 'align' . $align; }
    if ( $class ) { $classes[] = $class; }
    if ( ! empty( $options['background'] ) ) {
        $classes[] = 'has-' . $options['background'] . '-background-color';
        $classes[] = 'has-background';
    }
    if ( ! empty( $options['text'] ) ) {
        $classes[] = 'has-' . $options['text'] . '-color';
        $classes[] = 'has-text-color';
    }
    $style = '';
    if ( ! empty( $options['padding'] ) ) {
        list( $vertical, $horizontal ) = $options['padding'];
        $style = ' style="padding-top:' . esc_attr( $vertical ) . ';padding-right:' . esc_attr( $horizontal ) . ';padding-bottom:' . esc_attr( $vertical ) . ';padding-left:' . esc_attr( $horizontal ) . '"';
    }

    return '<!-- wp:group ' . ks_attrs( $attrs ) . " -->\n<div class=\"" . implode( ' ', $classes ) . "\"{$style}>\n{$inner}\n</div>\n<!-- /wp:group -->";
}

function ks_columns( $columns, $class = '' ) {
    $attrs = $class ? array( 'className' => $class ) : array();
    $attr_json = $attrs ? ' ' . ks_attrs( $attrs ) : '';
    $html = '';
    foreach ( $columns as $column ) {
        $html .= "<!-- wp:column -->\n<div class=\"wp-block-column\">\n{$column}\n</div>\n<!-- /wp:column -->\n";
    }
    return "<!-- wp:columns{$attr_json} -->\n<div class=\"wp-block-columns {$class}\">\n{$html}</div>\n<!-- /wp:columns -->";
}

function ks_cover_hero( $image_id, $eyebrow, $heading, $lead, $buttons ) {
    $image = wp_get_attachment_image_src( $image_id, 'full' );
    $url = $image ? $image[0] : '';
    $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
    $attrs = array(
        'url' => $url,
        'id' => $image_id,
        'dimRatio' => 70,
        'overlayColor' => 'contrast',
        'isUserOverlayColor' => true,
        'align' => 'full',
        'className' => 'ks-page-hero',
        'style' => array( 'spacing' => array( 'padding' => array( 'top' => '7rem', 'bottom' => '7rem', 'left' => '32px', 'right' => '32px' ) ) ),
        'layout' => array( 'type' => 'constrained', 'wideSize' => '1120px' ),
    );
    $inner = ks_paragraph( esc_html( $eyebrow ), 'ks-page-eyebrow' ) . "\n\n"
           . ks_heading( $heading, 1 ) . "\n\n"
           . ks_paragraph( esc_html( $lead ), 'ks-page-lead' ) . "\n\n"
           . ks_buttons( $buttons );

    return '<!-- wp:cover ' . ks_attrs( $attrs ) . " -->\n"
         . '<div class="wp-block-cover alignfull ks-page-hero" style="padding-top:7rem;padding-right:32px;padding-bottom:7rem;padding-left:32px">'
         . '<span aria-hidden="true" class="wp-block-cover__background has-contrast-background-color has-background-dim-70 has-background-dim"></span>'
         . '<img class="wp-block-cover__image-background wp-image-' . (int) $image_id . '" alt="' . esc_attr( $alt ) . '" src="' . esc_url( $url ) . '" data-object-fit="cover"/>'
         . "<div class=\"wp-block-cover__inner-container\">{$inner}</div></div>\n<!-- /wp:cover -->";
}

function ks_city_pages() {
    $pages = get_posts( array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_key' => '_location_city',
        'orderby' => 'title',
        'order' => 'ASC',
    ) );
    $result = array();
    foreach ( $pages as $page ) {
        $city = str_replace( ', PA', '', get_post_meta( $page->ID, '_location_city', true ) );
        $result[ $city ] = get_permalink( $page->ID );
    }
    return $result;
}

function ks_city_list( $cities, $links ) {
    $items = '';
    foreach ( $cities as $city ) {
        if ( empty( $links[ $city ] ) ) { throw new RuntimeException( "Missing location page for {$city}" ); }
        $items .= "<!-- wp:list-item -->\n<li><a href=\"" . esc_url( $links[ $city ] ) . '">' . esc_html( $city ) . "</a></li>\n<!-- /wp:list-item -->\n";
    }
    return '<!-- wp:list ' . ks_attrs( array( 'className' => 'ks-city-list' ) ) . " -->\n<ul class=\"wp-block-list ks-city-list\">{$items}</ul>\n<!-- /wp:list -->";
}

function ks_region_card( $name, $description, $cities, $links ) {
    $inner = ks_heading( $name, 2 ) . "\n\n"
           . ks_paragraph( esc_html( $description ) ) . "\n\n"
           . ks_city_list( $cities, $links );
    return ks_group( $inner, 'ks-region-card', array( 'layout' => array( 'type' => 'constrained' ) ) );
}

function ks_update_page( $slug, $content, $excerpt, $meta_description ) {
    $page = get_page_by_path( $slug, OBJECT, 'page' );
    if ( ! $page ) { throw new RuntimeException( "Page not found: {$slug}" ); }
    wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content, 'post_excerpt' => $excerpt ) );
    update_post_meta( $page->ID, '_yoast_wpseo_metadesc', $meta_description );
    echo "UPDATED {$slug} (#{$page->ID})\n";
}

$links = ks_city_pages();

$regions = array(
    array( 'Eastern Pennsylvania', 'Rowhouses, twins, borough homes, and suburban systems from the Lehigh Valley through the western Philadelphia suburbs.', array( 'Allentown', 'Bethlehem', 'Coatesville', 'Doylestown', 'Easton', 'King of Prussia', 'Media', 'Norristown', 'Phoenixville', 'Pottstown', 'Quakertown', 'Reading', 'West Chester' ) ),
    array( 'South Central Pennsylvania', 'Historic districts, farmhouses, river humidity, and growing communities across the Susquehanna and Cumberland valleys.', array( 'Carlisle', 'Chambersburg', 'Columbia', 'Ephrata', 'Gettysburg', 'Hanover', 'Harrisburg', 'Hershey', 'Lancaster', 'Lebanon', 'Lewisburg', 'Lititz', 'Mechanicsburg', 'Selinsgrove', 'Shippensburg', 'Waynesboro', 'York' ) ),
    array( 'Western Pennsylvania', 'Steel-town twins, hillside rowhouses, older boiler systems, and weather that asks heating equipment to earn its keep.', array( 'Altoona', 'Beaver Falls', 'Butler', 'Greensburg', 'Indiana', 'Johnstown', 'New Castle', 'Pittsburgh' ) ),
    array( 'Northern, Lake and Pocono Pennsylvania', 'Lake-effect winter, mountain elevation, second homes, steam heat, and freeze protection across the northern tier.', array( 'Bradford', 'DuBois', 'East Stroudsburg', 'Erie', 'Hazleton', 'Meadville', 'Oil City', 'Scranton', 'State College', 'Stroudsburg', 'Warren', 'Wilkes-Barre', 'Williamsport' ) ),
);

$region_cards = '';
$seen = array();
foreach ( $regions as $region ) {
    foreach ( $region[2] as $city ) {
        if ( isset( $seen[ $city ] ) ) { throw new RuntimeException( "Duplicate region assignment: {$city}" ); }
        $seen[ $city ] = true;
    }
    $region_cards .= ks_region_card( $region[0], $region[1], $region[2], $links ) . "\n";
}
if ( count( $seen ) !== 51 ) { throw new RuntimeException( 'Region map contains ' . count( $seen ) . ' cities, expected 51' ); }

$service_hero = ks_cover_hero(
    175,
    'Service areas · 51 Pennsylvania communities',
    'A local page for the house you actually have',
    'Pennsylvania homes change block by block. Choose your community for guidance shaped around its housing stock, weather, and common comfort problems.',
    array(
        array( 'label' => 'Browse every community', 'url' => '#all-communities' ),
        array( 'label' => 'Request an assessment', 'url' => '/contact/', 'outline' => true ),
    )
);

$metrics = ks_group(
    ks_columns( array(
        ks_paragraph( '<strong>51</strong> community guides' ),
        ks_paragraph( '<strong>4</strong> Pennsylvania regions' ),
        ks_paragraph( '<strong>1</strong> measurement-first approach' ),
    ) ),
    'ks-metric-strip',
    array( 'align' => 'wide', 'padding' => array( '0px', '24px' ) )
);

$regions_inner = ks_paragraph( 'Find your town', 'ks-eyebrow', 'center' ) . "\n\n"
               . ks_heading( 'Every community guide', 2, '', 'center' ) . "\n\n"
               . ks_paragraph( 'Each page covers a different local question rather than repeating the same sales copy with a city name swapped in.', '', 'center' ) . "\n\n"
               . ks_group( $region_cards, 'ks-region-grid', array( 'layout' => array( 'type' => 'default' ) ) );
$regions_section = ks_group( $regions_inner, 'ks-regions', array( 'align' => 'full', 'background' => 'tint', 'padding' => array( '72px', '24px' ) ) );
$regions_section = str_replace( '<div class="wp-block-group alignfull ks-regions', '<div id="all-communities" class="wp-block-group alignfull ks-regions', $regions_section );

$service_cta = ks_group(
    ks_heading( 'Not sure which guide applies?', 2, '', 'center' ) . "\n\n"
    . ks_paragraph( 'Tell us the town, the age of the house, and what the system is doing. We will start with the right questions.', '', 'center' ) . "\n\n"
    . ks_buttons( array( array( 'label' => 'Start the conversation', 'url' => '/contact/' ) ), 'center' ),
    'ks-final-cta',
    array( 'align' => 'full', 'padding' => array( '72px', '24px' ) )
);

ks_update_page(
    'service-areas',
    $service_hero . "\n\n" . $metrics . "\n\n" . $regions_section . "\n\n" . $service_cta,
    'Explore 51 Pennsylvania community guides organized by region, housing stock, climate, and common HVAC concerns.',
    'Explore Keystone Comfort community guides for 51 Pennsylvania service areas, organized by region and local housing needs.'
);

$contact_hero = ks_cover_hero(
    188,
    'Contact · Measurement before recommendations',
    'Tell us what the house is doing',
    'Start with the symptoms: uneven rooms, short cycling, humidity, noise, high bills, or a system that simply stopped. The first useful answer comes from the house, not a phone quote.',
    array(
        array( 'label' => 'Call (717) 555-0148', 'url' => 'tel:+17175550148' ),
        array( 'label' => 'Check your service area', 'url' => '/service-areas/', 'outline' => true ),
    )
);

$form_html = <<<'HTML'
<!-- wp:html -->
<form class="ks-contact-form" aria-describedby="ks-demo-form-note">
  <p class="ks-page-eyebrow">Assessment request</p>
  <h2>Give us the useful details</h2>
  <div class="ks-contact-form-grid">
    <label>Name<input type="text" name="name" autocomplete="name" placeholder="Your name"></label>
    <label>Phone<input type="tel" name="phone" autocomplete="tel" placeholder="(555) 555-5555"></label>
    <label>Email<input type="email" name="email" autocomplete="email" placeholder="you@example.com"></label>
    <label>Community<input type="text" name="community" autocomplete="address-level2" placeholder="Town or borough"></label>
    <label class="ks-field-full">What is the house doing?<textarea name="symptoms" placeholder="Tell us what changed, which rooms are affected, and when it happens."></textarea></label>
  </div>
  <button class="ks-demo-submit" type="button" aria-disabled="true">Demo form — connect Gravity Forms</button>
  <p id="ks-demo-form-note" class="ks-demo-note">This pilot does not collect submissions. Production customer sites would connect the approved form and consent workflow here.</p>
</form>
<!-- /wp:html -->
HTML;

$contact_panel = <<<'HTML'
<!-- wp:html -->
<aside class="ks-contact-panel">
  <p class="ks-page-eyebrow">Direct contact</p>
  <h2>Keystone Comfort Co.</h2>
  <p><strong>Phone</strong><br><a href="tel:+17175550148">(717) 555-0148</a></p>
  <p><strong>Hours</strong><br>Weekdays, 7:30–5:00</p>
  <p><strong>Coverage</strong><br>51 Pennsylvania communities</p>
  <hr>
  <h3>Helpful before the visit</h3>
  <ul>
    <li>System age, if known</li>
    <li>Which rooms feel different</li>
    <li>When the problem started</li>
    <li>Recent utility bill changes</li>
  </ul>
</aside>
<!-- /wp:html -->
HTML;

$contact_main = ks_group(
    ks_group( $form_html . "\n" . $contact_panel, 'ks-contact-grid', array( 'layout' => array( 'type' => 'default' ) ) ),
    'ks-contact-section',
    array( 'align' => 'full', 'background' => 'tint', 'padding' => array( '72px', '24px' ) )
);

$process = ks_group(
    ks_paragraph( 'What happens next', 'ks-eyebrow', 'center' ) . "\n\n"
    . ks_heading( 'A clear first conversation', 2, '', 'center' ) . "\n\n"
    . ks_columns( array(
        ks_heading( '1. Describe the symptoms', 3 ) . ks_paragraph( 'We start with what changed, where it happens, and whether the problem is urgent.' ),
        ks_heading( '2. Confirm the right visit', 3 ) . ks_paragraph( 'The house type, system, and location determine what should be measured on site.' ),
        ks_heading( '3. Compare the options', 3 ) . ks_paragraph( 'After measurement, repair and replacement options are explained against the same set of facts.' ),
    ), 'ks-process' ),
    'ks-process-section',
    array( 'align' => 'full', 'padding' => array( '72px', '24px' ) )
);

$disclosure = ks_group(
    ks_paragraph( '<strong>Pilot disclosure:</strong> Keystone Comfort Co. is a fictional demonstration brand. The phone number is a reserved fictional 555 number, and the form above is intentionally disconnected.', 'ks-disclosure' ),
    '',
    array( 'align' => 'full', 'padding' => array( '28px', '24px' ), 'wide' => '900px' )
);

ks_update_page(
    'contact',
    $contact_hero . "\n\n" . $contact_main . "\n\n" . $process . "\n\n" . $disclosure,
    'Describe what your Pennsylvania house is doing and prepare for a measurement-first HVAC assessment.',
    'Contact the Keystone Comfort pilot to request a measurement-first HVAC assessment in one of 51 Pennsylvania communities.'
);
