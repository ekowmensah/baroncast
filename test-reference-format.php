<?php
/**
 * Test New Transaction Reference Format
 * Demonstrates the enhanced reference generation
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<h2>🔖 New Transaction Reference Format</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

// Test cases
$test_cases = [
    [
        'event' => 'Best Group Dancers',
        'nominee' => 'Darius'
    ],
    [
        'event' => 'Annual Music Awards 2024',
        'nominee' => 'John Doe'
    ],
    [
        'event' => 'Outstanding Performance in Drama',
        'nominee' => 'Sarah Johnson'
    ],
    [
        'event' => 'Young Entrepreneur of the Year',
        'nominee' => 'Michael Brown'
    ],
    [
        'event' => 'Best DJ Mix',
        'nominee' => 'DJ Awesome'
    ]
];

echo "<h3>📋 Reference Format Examples:</h3>";

foreach ($test_cases as $i => $case) {
    echo "<div style='background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff;'>";
    echo "<h4>Example " . ($i + 1) . ":</h4>";
    
    // Generate reference using the same logic as the updated code
    $event_words = explode(' ', $case['event']);
    $event_abbr = '';
    foreach ($event_words as $word) {
        if (!empty($word)) {
            $event_abbr .= strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $word), 0, 1));
        }
    }
    
    $nominee_clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $case['nominee']));
    $transaction_ref = $event_abbr . $nominee_clean . '-' . date('mdHi') . '-' . rand(100, 999);
    
    echo "<strong>Event:</strong> " . $case['event'] . "<br>";
    echo "<strong>Nominee:</strong> " . $case['nominee'] . "<br>";
    echo "<strong>Event Abbreviation:</strong> " . $event_abbr . " (";
    
    // Show how abbreviation is formed
    $abbr_breakdown = [];
    foreach ($event_words as $word) {
        if (!empty($word)) {
            $clean_word = preg_replace('/[^A-Za-z0-9]/', '', $word);
            if (!empty($clean_word)) {
                $abbr_breakdown[] = "<span style='color: #007bff;'>" . strtoupper(substr($clean_word, 0, 1)) . "</span>" . substr($clean_word, 1);
            }
        }
    }
    echo implode(' + ', $abbr_breakdown) . ")<br>";
    
    echo "<strong>Nominee Clean:</strong> " . $nominee_clean . "<br>";
    echo "<strong>Generated Reference:</strong> <code style='background: #e9ecef; padding: 2px 6px; border-radius: 3px; color: #d63384; font-weight: bold;'>" . $transaction_ref . "</code><br>";
    echo "</div>";
}

echo "<br><h3>🔧 Format Breakdown:</h3>";
echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
echo "<h4>Reference Structure:</h4>";
echo "<code>[EVENT_ABBREVIATION][NOMINEE_NAME]-[MMDDHHII]-[RANDOM]</code><br><br>";

echo "<strong>Components:</strong><br>";
echo "• <strong>EVENT_ABBREVIATION:</strong> First letter of each word in event name<br>";
echo "• <strong>NOMINEE_NAME:</strong> Full nominee name (cleaned, no spaces/special chars)<br>";
echo "• <strong>MMDDHHII:</strong> Month, Day, Hour, Minute (e.g., 1228 = Dec 28, 12:28)<br>";
echo "• <strong>RANDOM:</strong> 3-digit random number for uniqueness<br>";
echo "</div>";

echo "<br><h3>✅ Improvements Made:</h3>";
echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
echo "<h4>Enhanced Reference Format:</h4>";
echo "<ul>";
echo "<li><strong>More Descriptive:</strong> Uses full event abbreviation instead of just 4 chars</li>";
echo "<li><strong>Complete Nominee Name:</strong> Uses entire nominee name for clarity</li>";
echo "<li><strong>Better Separators:</strong> Uses hyphens (-) instead of underscores for readability</li>";
echo "<li><strong>Consistent Format:</strong> Same logic for both PayProxy and Direct Mobile Money</li>";
echo "<li><strong>Easy to Read:</strong> Clear structure that's human-readable</li>";
echo "</ul>";
echo "</div>";

echo "<br><h3>📝 Examples in Action:</h3>";
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
echo "<h4>Real-world Examples:</h4>";
echo "<table style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #f8f9fa;'>";
echo "<th style='padding: 8px; border: 1px solid #dee2e6; text-align: left;'>Event</th>";
echo "<th style='padding: 8px; border: 1px solid #dee2e6; text-align: left;'>Nominee</th>";
echo "<th style='padding: 8px; border: 1px solid #dee2e6; text-align: left;'>Reference</th>";
echo "</tr>";

$examples = [
    ['Best Group Dancers', 'Darius', 'BGDDARIUS-1228-456'],
    ['Annual Music Awards', 'John Doe', 'AMAJOHNDOE-1228-789'],
    ['Outstanding Performance Drama', 'Sarah Johnson', 'OPDSARAHJOHNSON-1228-123'],
    ['Young Entrepreneur Year', 'Michael Brown', 'YEYMICHAELBROWN-1228-567'],
    ['Best DJ Mix', 'DJ Awesome', 'BDMDJAWESOME-1228-890']
];

foreach ($examples as $example) {
    echo "<tr>";
    echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . $example[0] . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #dee2e6;'>" . $example[1] . "</td>";
    echo "<td style='padding: 8px; border: 1px solid #dee2e6;'><code>" . $example[2] . "</code></td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<button onclick=\"window.location.reload()\" style='background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🔄 Generate New Examples</button>";
echo "</div>";
?>
