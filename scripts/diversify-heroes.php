<?php
/**
 * Rewrite hero types across all specs to get an even mix.
 * Also varies CTA text per hero type.
 * Run: php scripts/diversify-heroes.php
 */

$hero_cycle = ['hero_cover', 'hero_split', 'hero_offset', 'hero_cover', 'hero_split', 'hero_offset', 'hero_cover', 'hero_split'];
$cta_options = [
    'Book the Assessment',
    'Get a System Design',
    'Schedule the Walkthrough',
    'Book Winter Readiness',
    'Get Your Numbers',
    'Request a Quote',
];

$idx = 0;
for ($f = 0; $f <= 5; $f++) {
    $path = __DIR__ . "/../content/specs/layout-{$f}.json";
    $specs = json_decode(file_get_contents($path), true);

    foreach ($specs as &$spec) {
        if (empty($spec['sections'])) continue;

        $hero_type = $hero_cycle[$idx % count($hero_cycle)];
        $cta = $cta_options[$idx % count($cta_options)];
        $spec['sections'][0]['type'] = $hero_type;
        $spec['sections'][0]['cta'] = $cta;

        // For split/offset heroes, ensure 'text' field exists (they use 'text' not 'subline')
        if (in_array($hero_type, ['hero_split', 'hero_offset'])) {
            if (empty($spec['sections'][0]['text']) && !empty($spec['sections'][0]['subline'])) {
                $spec['sections'][0]['text'] = $spec['sections'][0]['subline'];
            }
        }

        $idx++;
    }
    unset($spec);

    file_put_contents($path, json_encode($specs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "layout-{$f}.json: " . count($specs) . " specs updated\n";
}

echo "\nTotal: {$idx} pages diversified across " . count(array_unique($hero_cycle)) . " hero types\n";
