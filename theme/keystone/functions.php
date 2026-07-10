<?php
add_action( 'wp_enqueue_scripts', function() {
    if ( is_singular( 'page' ) && get_post_meta( get_the_ID(), '_location_city', true ) ) {
        wp_enqueue_style(
            'keystone-location',
            get_stylesheet_directory_uri() . '/assets/css/location.css',
            array(),
            filemtime( get_stylesheet_directory() . '/assets/css/location.css' )
        );
    }
} );

add_filter( 'get_block_templates', function( $query_result, $query, $template_type ) {
    return $query_result;
}, 10, 3 );
