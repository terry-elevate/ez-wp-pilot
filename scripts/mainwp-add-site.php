<?php
// Connect the pilot child site to the MainWP dashboard programmatically.
use MainWP\Dashboard\MainWP_Manage_Sites_View;

$params = array(
    'url'     => 'http://host.docker.internal:8181/',
    'name'    => 'EZ Pilot Site',
    'wpadmin' => 'terry',
    'adminpwd' => rawurlencode( 'elevate-Dev-8181' ),
    'unique_id' => '',
    'ssl_verify' => false,
);

$output  = array();
$website = false;
$result  = MainWP_Manage_Sites_View::add_wp_site( $website, $params, $output );

echo "RESULT:\n";
var_export( $result );
echo "\nOUTPUT:\n";
var_export( $output );
echo "\n";
