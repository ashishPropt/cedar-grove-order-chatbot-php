<?php
// api/chat.php — stateless JSON API consumed by the JS frontend
// All conversation state lives in the browser (sessionStorage); PHP just answers questions.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

require_once __DIR__ . '/../src/menu_data.php';
require_once __DIR__ . '/../config/env.php';

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

$menu = get_menu();

switch ($action) {

    // ── Step 1: return restaurant list ─────────────────────────────────
    case 'get_restaurants':
        echo json_encode(['restaurants' => $menu['restaurants']]);
        break;

    // ── Step 2: return categories for a restaurant ─────────────────────
    case 'get_categories':
        $restaurant = $body['restaurant'] ?? '';
        $cats = $menu['categories'][$restaurant] ?? [];
        echo json_encode(['categories' => $cats]);
        break;

    // ── Step 3: return items for a category ────────────────────────────
    case 'get_items':
        $category = $body['category'] ?? '';
        $data     = $menu['items'][$category] ?? null;
        if (!$data) { echo json_encode(['error' => 'Unknown category']); break; }
        echo json_encode(['items' => $data['items']]);
        break;

    // ── Step 4: return size options (null = no size choice) ────────────
    case 'get_sizes':
        $category = $body['category'] ?? '';
        $item     = $body['item']     ?? '';
        $sizes    = get_size_options($category, $item);

        $price_map = [];
        if ($sizes) {
            foreach ($sizes as $sz) {
                $price_map[$sz] = get_base_price($category, $item, $sz);
            }
        } else {
            $price_map['_fixed'] = get_base_price($category, $item, null);
        }
        echo json_encode(['sizes' => $sizes, 'price_map' => $price_map]);
        break;

    // ── Step 5: return modifier groups for a category ──────────────────
    case 'get_modifiers':
        $category = $body['category'] ?? '';
        $data     = $menu['items'][$category] ?? null;
        if (!$data) { echo json_encode(['error' => 'Unknown category']); break; }
        echo json_encode(['modifiers' => $data['modifiers'] ?? []]);
        break;

    // ── Step 6: validate & price a complete order item ─────────────────
    case 'price_item':
        $category   = $body['category']   ?? '';
        $item       = $body['item']        ?? '';
        $size_key   = $body['size_key']    ?? null;
        $selections = $body['selections']  ?? [];

        $data = $menu['items'][$category] ?? null;
        if (!$data) { echo json_encode(['error' => 'Unknown category']); break; }

        $base      = get_base_price($category, $item, $size_key);
        $mod_total = 0.0;
        $mod_lines = [];

        $modifiers = $data['modifiers'] ?? [];
        foreach ($selections as $mod_key => $chosen_names) {
            $mod = $modifiers[$mod_key] ?? null;
            if (!$mod) continue;
            foreach ((array) $chosen_names as $name) {
                if ($name === 'None') continue;
                foreach ($mod['options'] as [$opt_name, $opt_price]) {
                    if ($opt_name === $name) {
                        $mod_total += $opt_price;
                        $mod_lines[] = ['label' => $mod['label'] . ': ' . $name, 'cost' => $opt_price];
                        break;
                    }
                }
            }
        }

        $line_total = $base + $mod_total;
        echo json_encode([
            'name'       => $item . ($size_key ? " ($size_key)" : ''),
            'base_price' => $base,
            'mod_total'  => $mod_total,
            'mod_lines'  => $mod_lines,
            'line_total' => $line_total,
        ]);
        break;

    // ── Step 7: save completed order to Supabase ───────────────────────
    case 'save_order':
        $cart       = $body['cart']       ?? [];
        $order_type = $body['order_type'] ?? 'pickup';
        $subtotal   = array_sum(array_column($cart, 'line_total'));
        $tax        = round($subtotal * 0.0663, 2);
        $total      = round($subtotal + $tax, 2);

        // 1. Insert the order row
        $order_payload = json_encode([
            'status'     => 'pending',
            'order_type' => $order_type,
            'subtotal'   => $subtotal,
            'tax'        => $tax,
            'total'      => $total,
        ]);

        $ch = curl_init(SUPABASE_URL . '/rest/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $order_payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: '        . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . SUPABASE_ANON_KEY,
                'Prefer: return=representation',
            ],
        ]);

        $order_res  = curl_exec($ch);
        $order_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($order_http >= 300) {
            http_response_code(502);
            echo json_encode(['error' => 'Failed to create order', 'detail' => $order_res]);
            break;
        }

        $order_data = json_decode($order_res, true);
        $order_id   = $order_data[0]['id'] ?? null;

        if (!$order_id) {
            http_response_code(502);
            echo json_encode(['error' => 'Order created but ID not returned']);
            break;
        }

        // 2. Insert order_items
        $items_payload = [];
        foreach ($cart as $cart_item) {
            $items_payload[] = [
                'order_id'   => $order_id,
                'item_name'  => $cart_item['name'],
                'unit_price' => $cart_item['base_price'],
                'mod_total'  => $cart_item['mod_total'],
                'line_total' => $cart_item['line_total'],
                'quantity'   => 1,
            ];
        }

        $ch2 = curl_init(SUPABASE_URL . '/rest/v1/order_items');
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($items_payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'apikey: '        . SUPABASE_ANON_KEY,
                'Authorization: Bearer ' . SUPABASE_ANON_KEY,
                'Prefer: return=minimal',
            ],
        ]);

        $items_http = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_exec($ch2);
        curl_close($ch2);

        echo json_encode([
            'success'  => true,
            'order_id' => $order_id,
            'total'    => $total,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown action: $action"]);
        break;
}
