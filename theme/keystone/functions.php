<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'keystone-site',
        get_stylesheet_uri(),
        array(),
        filemtime( get_stylesheet_directory() . '/style.css' )
    );
}, 5 );

add_action( 'wp_enqueue_scripts', function() {
    if ( is_singular( 'page' ) && get_post_meta( get_the_ID(), '_location_city', true ) ) {
        wp_enqueue_style(
            'keystone-google-fonts',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@400;500;600&family=Oswald:wght@400;600&family=Roboto:wght@400;500&family=Barlow+Condensed:wght@600;700&family=Cabin:wght@400;500&display=swap',
            array(),
            null
        );
        wp_enqueue_style(
            'keystone-location',
            get_stylesheet_directory_uri() . '/assets/css/location.css',
            array( 'keystone-google-fonts' ),
            filemtime( get_stylesheet_directory() . '/assets/css/location.css' )
        );
        wp_enqueue_style(
            'keystone-brand-overrides',
            get_stylesheet_directory_uri() . '/assets/css/brand-overrides.css',
            array( 'keystone-location' ),
            filemtime( get_stylesheet_directory() . '/assets/css/brand-overrides.css' )
        );
    }
} );

add_filter( 'body_class', function( $classes ) {
    if ( is_singular( 'page' ) ) {
        $layout_type = get_post_meta( get_the_ID(), '_layout_type', true );
        if ( $layout_type ) {
            $slug = sanitize_title( $layout_type );
            $classes[] = 'layout-' . $slug;
        }
        $design_family = get_post_meta( get_the_ID(), '_design_family', true );
        if ( $design_family ) {
            $classes[] = 'design-' . sanitize_title( $design_family );
        }
        $brand = get_post_meta( get_the_ID(), '_brand_palette', true );
        if ( $brand ) {
            $classes[] = 'brand-' . sanitize_title( $brand );
        }
    }
    return $classes;
} );

add_filter( 'get_block_templates', function( $query_result, $query, $template_type ) {
    return $query_result;
}, 10, 3 );
