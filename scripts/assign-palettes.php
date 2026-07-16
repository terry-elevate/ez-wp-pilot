<?php
/**
 * Assign _brand_palette meta to all location pages.
 * Eastern PA = default (Bethlehem blue), Western PA = warm-industrial, Northern/Lake = lakefront-bold
 * Run: docker compose run --rm --entrypoint "" wpcli wp eval-file /var/www/html/scripts/assign-palettes.php
 */

$palettes = [
    // Western PA → warm-industrial (Pittsburgh gold)
    'warm-industrial' => [
        94,  // Pittsburgh
        161, // Greensburg
        162, // Johnstown
        163, // Indiana
        164, // Butler
        165, // Beaver Falls
        166, // New Castle
    ],
    // Northern/Lake PA → lakefront-bold (Erie brick-red)
    'lakefront-bold' => [
        93,  // Erie
        167, // Meadville
        168, // Oil City
        169, // Warren
        170, // Bradford
        171, // DuBois
    ],
    // Heritage green (Lancaster region)
    'heritage-serif' => [
        87,  // Lancaster
        155, // Lititz
        156, // Columbia
        137, // Ephrata
    ],
];

// Everything else stays default (Bethlehem blue from location.css :root)

$count = 0;
foreach ( $palettes as $palette => $ids ) {
    foreach ( $ids as $pid ) {
        update_post_meta( $pid, '_brand_palette', $palette );
        $title = get_the_title( $pid );
        echo "  {$palette} → {$pid} ({$title})\n";
        $count++;
    }
}
echo "\nAssigned palette to {$count} pages. Remaining pages use default Bethlehem blue.\n";
