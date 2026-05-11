<?php
session_start();
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'POST only']); exit; }
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error'=>'Bad JSON']); exit; }

$edit_id = $body['edit_id'] ?? null;

$entry = [
    'id'         => $edit_id ?: uniqid('bi_', true),  // keep same id when editing
    'item_id'    => $body['item_id']    ?? '',
    'item_name'  => $body['item_name']  ?? 'Unknown',
    'size_label' => $body['size_label'] ?? '',
    'base_price' => (float)($body['base_price'] ?? 0),
    'selections' => $body['selections'] ?? [],
    'total'      => (float)($body['total'] ?? 0),
    'qty'        => 1,
];

if ($edit_id) {
    // Replace existing entry in basket
    $replaced = false;
    foreach ($_SESSION['basket'] as &$b) {
        if ($b['id'] === $edit_id) {
            $entry['qty'] = $b['qty'];  // preserve qty
            $b = $entry;
            $replaced = true;
            break;
        }
    }
    if (!$replaced) {
        $_SESSION['basket'][] = $entry;  // fallback: add as new
    }
} else {
    $_SESSION['basket'][] = $entry;
}

echo json_encode([
    'ok'    => true,
    'count' => array_sum(array_column($_SESSION['basket'], 'qty')),
]);
