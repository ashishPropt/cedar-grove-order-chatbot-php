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

        // Build label => price map for the frontend
        $data = $menu['items'][$category] ?? [];
        $price_map = [];
        if ($sizes) {
            foreach ($sizes as $sz) {
                $price_map[$sz] = get_base_price($category, $item, $sz);
            }
        } else {
            // Fixed price — return it so the frontend can skip the size step
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
        $category  = $body['category']  ?? '';
        $item      = $body['item']      ?? '';
        $size_key  = $body['size_key']  ?? null;
        $selections = $body['selections'] ?? [];   // ['mod_key' => ['Option A', 'Option B']]

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
            'name'        => $item . ($size_key ? " ($size_key)" : ''),
            'base_price'  => $base,
            'mod_total'   => $mod_total,
            'mod_lines'   => $mod_lines,
            'line_total'  => $line_total,
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown action: $action"]);
        break;
}
