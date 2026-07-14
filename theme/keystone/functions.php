<?php
add_action( 'wp_enqueue_scripts', function() {
    if ( is_singular( 'page' ) && get_post_meta( get_the_ID(), '_location_city', true ) ) {
        wp_enqueue_style(
            'keystone-location',
            get_stylesheet_directory_uri() . '/assets/css/location.css',
            array(),
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
            $classes[] = 'brand-' . $slug;
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
