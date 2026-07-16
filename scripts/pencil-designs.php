<?php
/**
 * Canonical design contracts derived from approved Pencil comps.
 *
 * Content generation may populate copy and imagery, but it must not replace the
 * design family, brand palette, or canonical component variants defined here.
 */

function pencil_design_registry() {
    return array(
        'bethlehem-split-industrial' => array(
            'label'         => 'Bethlehem HVAC — Split Industrial',
            'source'        => 'tmp-bethlehem-design.html',
            'brand_palette' => 'bethlehem',
            'hero_type'     => 'hero_pencil_split',
            'hero_eyebrow'  => 'Bethlehem, PA · HVAC Services',
            'required_sections' => array(
                'hero_pencil_split',
                'trust_bar',
                'feature_row',
                'faq',
                'nearby',
                'cta_fullbleed',
            ),
        ),
    );
}

/**
 * Apply the immutable parts of a Pencil design contract to a page spec.
 */
function pencil_apply_design_contract( $spec ) {
    $family = $spec['design_family'] ?? '';
    if ( ! $family ) { return $spec; }

    $registry = pencil_design_registry();
    if ( empty( $registry[ $family ] ) ) {
        throw new RuntimeException( "Unknown Pencil design family: {$family}" );
    }

    $contract = $registry[ $family ];
    $spec['brand_palette'] = $spec['brand_palette'] ?? $contract['brand_palette'];
    $spec['design_source'] = $contract['source'];

    foreach ( $spec['sections'] as $index => $section ) {
        if ( strpos( $section['type'], 'hero_' ) !== 0 ) { continue; }

        $section['type'] = $contract['hero_type'];
        $section['eyebrow'] = $section['eyebrow'] ?? $contract['hero_eyebrow'];
        if ( empty( $section['text'] ) && ! empty( $section['subline'] ) ) {
            $section['text'] = $section['subline'];
        }
        $spec['sections'][ $index ] = $section;
        break;
    }

    $positions = array();
    foreach ( $spec['sections'] as $index => $section ) {
        if ( ! isset( $positions[ $section['type'] ] ) ) {
            $positions[ $section['type'] ] = $index;
        }
    }
    $last_position = -1;
    foreach ( $contract['required_sections'] as $required_type ) {
        if ( ! isset( $positions[ $required_type ] ) ) {
            throw new RuntimeException( "Pencil design {$family} requires section {$required_type}" );
        }
        if ( $positions[ $required_type ] < $last_position ) {
            throw new RuntimeException( "Pencil design {$family} has an invalid section order at {$required_type}" );
        }
        $last_position = $positions[ $required_type ];
    }

    return $spec;
}
