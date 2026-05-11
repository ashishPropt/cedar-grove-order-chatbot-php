<?php
/**
 * api/menu.php - Returns full menu as JSON for offline caching
 * Called by service worker to pre-cache menu data
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$base = dirname(__DIR__);
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

echo json_encode(fetch_menu());
