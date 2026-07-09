<?php
// ACF acceleration demo (Deb's requirement, using the client's real example):
// natural-language spec -> local AI designs the field schema -> registered via ACF field API
// -> sample entries created -> front-end filter query proven (events vs programs).
global $mwai;

if ( ! function_exists( 'acf_import_field_group' ) ) {
    echo "ACF not active.\n";
    exit;
}

$spec = 'A community organization offers Events (one-time, has a date, a venue, and registration) '
      . 'and Programs (ongoing, has a schedule like "Tuesdays 6pm", an age group, and an instructor). '
      . 'Both have a short description and a capacity. Staff plug the info into fields; '
      . 'the public site filters activities by whether they are an event or a program.';

$prompt = <<<PROMPT
You design WordPress ACF (Advanced Custom Fields) schemas. Given this client spec, design ONE field group for a custom post type "activity".

Spec: {$spec}

Reply with ONLY a JSON object, no markdown fences:
{"group_title": string,
 "fields": [ {"label": string, "name": string (snake_case), "type": one of "text","textarea","number","url","date_picker","time_picker","select","true_false", "choices": object (only for select), "instructions": string (short, for staff)} ] }
Include a select field named "activity_type" with choices event/program. 6-9 fields total. Field order should match how staff would fill the form.
PROMPT;

$raw = $mwai->simpleTextQuery( $prompt );
$raw = preg_replace( '/^```[a-z]*\s*|```\s*$/m', '', trim( $raw ) );
$start = strpos( $raw, '{' );
$end   = strrpos( $raw, '}' );
if ( $start !== false && $end !== false ) {
    $raw = substr( $raw, $start, $end - $start + 1 );
}
$schema = json_decode( $raw, true );

if ( ! is_array( $schema ) || empty( $schema['fields'] ) ) {
    echo 'Model did not return valid JSON (' . json_last_error_msg() . '). Raw head: ' . substr( $raw, 0, 200 ) . "\n";
    echo "Raw tail: " . substr( $raw, -200 ) . "\n";
    exit;
}

echo "AI-designed field group: {$schema['group_title']} (" . count( $schema['fields'] ) . " fields)\n";

$acf_fields = array();
foreach ( $schema['fields'] as $i => $f ) {
    $field = array(
        'key'          => 'field_ai_activity_' . $f['name'],
        'label'        => $f['label'],
        'name'         => $f['name'],
        'type'         => $f['type'],
        'instructions' => $f['instructions'] ?? '',
    );
    if ( $f['type'] === 'select' && ! empty( $f['choices'] ) ) {
        $field['choices'] = $f['choices'];
    }
    $acf_fields[] = $field;
    printf( "  %-22s %-12s %s\n", $f['name'], $f['type'], $f['label'] );
}

acf_import_field_group( array(
    'key'      => 'group_ai_activity',
    'title'    => $schema['group_title'],
    'fields'   => $acf_fields,
    'location' => array( array( array(
        'param'    => 'post_type',
        'operator' => '==',
        'value'    => 'activity',
    ) ) ),
) );

$groups = acf_get_field_groups( array( 'post_type' => 'activity' ) );
echo "Registered field groups for CPT 'activity': " . count( $groups ) . "\n";

// Sample entries: one event, one program.
$samples = array(
    array( 'title' => 'Fire Safety Open House', 'type' => 'event' ),
    array( 'title' => 'Youth Robotics Club', 'type' => 'program' ),
);
foreach ( $samples as $s ) {
    $id = wp_insert_post( array(
        'post_title'  => $s['title'],
        'post_type'   => 'activity',
        'post_status' => 'publish',
    ) );
    update_field( 'field_ai_activity_activity_type', $s['type'], $id );
    echo "Created activity {$id}: {$s['title']} ({$s['type']})\n";
}

// Front-end filter proof: query only events.
$events = get_posts( array(
    'post_type'  => 'activity',
    'meta_query' => array( array( 'key' => 'activity_type', 'value' => 'event' ) ),
) );
echo "Filter query activity_type=event returns: " . count( $events ) . " post(s) — " .
    implode( ', ', wp_list_pluck( $events, 'post_title' ) ) . "\n";
