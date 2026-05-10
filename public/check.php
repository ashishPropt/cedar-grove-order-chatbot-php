<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$base = __DIR__;

echo "<pre>";
echo "base = $base\n";
echo "config/env.php exists: " . (file_exists($base . '/config/env.php') ? 'YES' : 'NO') . "\n";
echo "src/supabase.php exists: " . (file_exists($base . '/src/supabase.php') ? 'YES' : 'NO') . "\n";
echo "src/helpers.php exists: " . (file_exists($base . '/src/helpers.php') ? 'YES' : 'NO') . "\n";
echo "assets/css/app.css exists: " . (file_exists($base . '/assets/css/app.css') ? 'YES' : 'NO') . "\n";
echo "</pre>";

require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

echo "<p>Supabase URL: " . SUPABASE_URL . "</p>";

$restaurants = sb_get('restaurants', ['active' => 'eq.true', 'order' => 'name']);
echo "<p>Restaurants found: " . count($restaurants) . "</p>";
foreach ($restaurants as $r) {
    echo "<p>- " . htmlspecialchars($r['name']) . "</p>";
    $cats = sb_get('categories', ['restaurant_id' => 'eq.' . $r['id'], 'active' => 'eq.true', 'order' => 'display_order']);
    echo "<p>&nbsp;&nbsp;Categories: " . count($cats) . "</p>";
    foreach ($cats as $c) {
        $items = sb_get('menu_items', ['category_id' => 'eq.' . $c['id'], 'available' => 'eq.true', 'select' => 'id,name']);
        echo "<p>&nbsp;&nbsp;&nbsp;&nbsp;" . htmlspecialchars($c['name']) . " (" . count($items) . " items)</p>";
    }
}

echo "<p style='color:green'>All checks passed!</p>";
