<?php
/**
 * api/sync_orders.php - Receives offline orders and saves to Supabase
 * Called by service worker background sync
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$base = dirname(__DIR__);
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['orders'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No orders provided']);
    exit;
}

$results = [];
foreach ($body['orders'] as $order) {
    $basket     = $order['basket']     ?? [];
    $order_type = $order['order_type'] ?? 'dine_in';
    $name       = $order['name']       ?? '';
    $phone      = $order['phone']      ?? '';
    $email      = $order['email']      ?? '';
    $notes      = $order['notes']      ?? '';
    $offline_id = $order['offline_id'] ?? uniqid();

    $subtotal = array_sum(array_map(fn($b) => $b['total'] * ($b['qty'] ?? 1), $basket));
    $tax      = round($subtotal * 0.0663, 2);
    $total    = round($subtotal + $tax, 2);

    // Customer
    $customer = null;
    if ($email) {
        $existing = sb_get('customers', ['email' => 'eq.' . $email]);
        $customer = $existing[0] ?? null;
        if (!$customer) {
            $rows = sb_post('customers', ['name'=>$name,'email'=>$email,'phone'=>$phone]);
            $customer = $rows[0] ?? null;
        }
    }

    // Restaurant from first item
    $restaurant_id = null;
    if (!empty($basket)) {
        $rows = sb_get('menu_items', ['id'=>'eq.'.$basket[0]['item_id'], 'select'=>'restaurant_id']);
        $restaurant_id = $rows[0]['restaurant_id'] ?? null;
    }

    $order_payload = [
        'restaurant_id' => $restaurant_id,
        'status'        => 'pending',
        'order_type'    => $order_type,
        'subtotal'      => $subtotal,
        'tax'           => $tax,
        'total'         => $total,
        'notes'         => $notes . ($notes ? ' ' : '') . '[offline:'.$offline_id.']',
    ];
    if ($customer) $order_payload['customer_id'] = $customer['id'];

    $order_rows = sb_post('orders', $order_payload);
    $saved_order = $order_rows[0] ?? null;

    if ($saved_order) {
        foreach ($basket as $b) {
            for ($q = 0; $q < ($b['qty'] ?? 1); $q++) {
                $oi_rows = sb_post('order_items', [
                    'order_id'    => $saved_order['id'],
                    'menu_item_id'=> $b['item_id'],
                    'unit_price'  => $b['base_price'],
                    'line_total'  => $b['total'],
                    'quantity'    => 1,
                    'notes'       => $b['size_label'] ?? '',
                ]);
                $oi = $oi_rows[0] ?? null;
                if ($oi && !empty($b['selections'])) {
                    foreach ($b['selections'] as $sel) {
                        if (!empty($sel['option_id'])) {
                            sb_post('order_item_modifiers', [
                                'order_item_id'     => $oi['id'],
                                'modifier_option_id'=> $sel['option_id'],
                                'price_delta'       => $sel['price_delta'] ?? 0,
                            ]);
                        }
                    }
                }
            }
        }
        $results[] = ['offline_id' => $offline_id, 'order_id' => $saved_order['id'], 'synced' => true];
    } else {
        $results[] = ['offline_id' => $offline_id, 'synced' => false, 'error' => 'Failed to save order'];
    }
}

echo json_encode(['ok' => true, 'results' => $results]);
