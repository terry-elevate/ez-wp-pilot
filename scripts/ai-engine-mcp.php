<?php
// Enable AI Engine's MCP module + core WP tools, with a bearer token for auth.
// Prints the mcp/module option keys before and after so we can verify shape.
$options = get_option( 'mwai_options', array() );

echo "BEFORE (mcp/module keys):\n";
foreach ( $options as $k => $v ) {
    if ( strpos( $k, 'mcp' ) !== false || strpos( $k, 'module' ) !== false ) {
        echo "  $k = " . json_encode( $v ) . "\n";
    }
}

$options['module_mcp']       = true;
$options['mcp_core']         = true;
$options['mcp_bearer_token'] = 'ez-pilot-mcp-Vk49x';

update_option( 'mwai_options', $options );

$options = get_option( 'mwai_options', array() );
echo "AFTER:\n";
foreach ( $options as $k => $v ) {
    if ( strpos( $k, 'mcp' ) !== false || strpos( $k, 'module' ) !== false ) {
        echo "  $k = " . json_encode( $v ) . "\n";
    }
}
echo "DONE\n";
