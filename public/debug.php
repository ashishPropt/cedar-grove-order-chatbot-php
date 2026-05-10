<?php
/**
 * Diagnostic page — visit /debug.php to check server config
 * DELETE THIS FILE after everything is working.
 */
echo "<pre>";
echo "PHP version: " . PHP_VERSION . "\n";
echo "__FILE__: " . __FILE__ . "\n";
echo "dirname(__DIR__): " . dirname(__DIR__) . "\n\n";

$paths = [
    dirname(__DIR__) . '/config/env.php',
    __DIR__ . '/config/env.php',
    __DIR__ . '/../config/env.php',
];
foreach ($paths as $p) {
    echo "Checking: $p => " . (file_exists($p) ? 'FOUND' : 'not found') . "\n";
}

echo "\nDirectory listing of parent (" . dirname(__DIR__) . "):\n";
foreach (scandir(dirname(__DIR__)) as $f) echo "  $f\n";

echo "\nDirectory listing of __DIR__ (" . __DIR__ . "):\n";
foreach (scandir(__DIR__) as $f) echo "  $f\n";

// Test Supabase if env loaded
foreach ($paths as $p) {
    if (file_exists($p)) { require_once $p; break; }
}
if (defined('SUPABASE_URL')) {
    echo "\nSupabase URL: " . SUPABASE_URL . "\n";
    $ch = curl_init(SUPABASE_URL . '/rest/v1/restaurants?select=name&limit=3');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Supabase HTTP status: $code\n";
    echo "Supabase response: $res\n";
} else {
    echo "\nenv.php not loaded — SUPABASE_URL not defined\n";
}
echo "</pre>";
