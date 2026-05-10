<?php
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error'=>'Bad JSON']); exit; }
$entry = [
    'id'         => uniqid('bi_', true),
    'item_id'    => $body['item_id']    ?? '',
    'item_name'  => $body['item_name']  ?? 'Unknown',
    'size_label' => $body['size_label'] ?? '',
    'base_price' => (float)($body['base_price'] ?? 0),
    'selections' => $body['selections'] ?? [],
    'total'      => (float)($body['total'] ?? 0),
    'qty'        => 1,
];
$_SESSION['basket'][] = $entry;
echo json_encode(['ok' => true, 'count' => array_sum(array_column($_SESSION['basket'], 'qty'))]);
