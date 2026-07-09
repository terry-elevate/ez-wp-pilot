<?php
// Activity CPT for the Events/Programs demo (client example from discovery call).
add_action( "init", function () {
    register_post_type( "activity", array(
        "label"        => "Activities",
        "public"       => true,
        "show_in_rest" => true,
        "supports"     => array( "title", "editor", "custom-fields" ),
        "has_archive"  => true,
    ) );
} );
