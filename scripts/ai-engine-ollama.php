<?php
// Add a local Ollama env (OpenAI-compatible endpoint) to AI Engine and make it default.
$options = get_option( 'mwai_options', array() );
$envs    = isset( $options['ai_envs'] ) ? $options['ai_envs'] : array();

$ollama_models = array(
    array(
        'model'               => 'gemma4:latest',
        'name'                => 'Gemma 4 (local)',
        'type'                => 'custom',
        'mode'                => 'chat',
        'features'            => array( 'completion' ),
        'tags'                => array( 'core', 'chat' ),
        'maxCompletionTokens' => 8192,
        'maxContextualTokens' => 131072,
    ),
    array(
        'model'               => 'qwen3-vl:30b',
        'name'                => 'Qwen3 VL 30B (local)',
        'type'                => 'custom',
        'mode'                => 'chat',
        'features'            => array( 'completion', 'vision' ),
        'tags'                => array( 'core', 'chat', 'vision' ),
        'maxCompletionTokens' => 8192,
        'maxContextualTokens' => 131072,
    ),
);

$ollama_env = array(
    'name'           => 'Ollama (Local)',
    'type'           => 'custom',
    'apikey'         => 'ollama',
    'endpoint'       => 'http://host.docker.internal:11434/v1',
    'dynamic_models' => true,
    'models'         => $ollama_models,
    'id'             => 'ollamaloc',
);

$found = false;
foreach ( $envs as $i => $env ) {
    if ( $env['id'] === 'ollamaloc' ) {
        $envs[ $i ] = $ollama_env;
        $found      = true;
        break;
    }
}
if ( ! $found ) {
    $envs[] = $ollama_env;
}

$options['ai_envs']          = $envs;
$options['ai_default_env']   = 'ollamaloc';
$options['ai_default_model'] = 'gemma4:latest';
$options['ai_fast_default_env'] = 'ollamaloc';

update_option( 'mwai_options', $options );
echo "AI Engine env configured. Default env: ollamaloc, model: gemma4:latest\n";
