<?php
function fmt(float $n): string { return '$' . number_format($n, 2); }
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** Fetch full menu: restaurants -> categories -> items+sizes */
function fetch_menu(): array {
    $restaurants = sb_get('restaurants', ['active' => 'eq.true', 'order' => 'name']);
    foreach ($restaurants as &$r) {
        $cats = sb_get('categories', [
            'restaurant_id' => 'eq.' . $r['id'],
            'active'        => 'eq.true',
            'order'         => 'display_order',
        ]);
        foreach ($cats as &$c) {
            $items = sb_get('menu_items', [
                'category_id' => 'eq.' . $c['id'],
                'available'   => 'eq.true',
                'order'       => 'display_order',
                'select'      => 'id,name,description,featured',
            ]);
            foreach ($items as &$item) {
                $sizes = sb_get('item_sizes', [
                    'menu_item_id' => 'eq.' . $item['id'],
                    'order'        => 'display_order',
                ]);
                $item['sizes']     = $sizes;
                $item['min_price'] = !empty($sizes)
                    ? min(array_column($sizes, 'price'))
                    : 0;
            }
            $c['items'] = $items;
        }
        $r['categories'] = $cats;
    }
    return $restaurants;
}

/** Fetch one item's modifiers for the chatbot */
function fetch_item_modifiers(string $item_id): array {
    // modifier groups linked to this item
    $links = sb_get('item_modifier_groups', [
        'menu_item_id' => 'eq.' . $item_id,
        'order'        => 'display_order',
        'select'       => 'modifier_group_id,display_order',
    ]);
    $result = [];
    foreach ($links as $link) {
        $groups = sb_get('modifier_groups', [
            'id'     => 'eq.' . $link['modifier_group_id'],
            'select' => 'id,name,ui_type,min_select,max_select',
        ]);
        if (empty($groups)) continue;
        $g = $groups[0];
        $g['options'] = sb_get('modifier_options', [
            'modifier_group_id' => 'eq.' . $g['id'],
            'order'             => 'display_order',
        ]);
        $result[] = $g;
    }
    return $result;
}

/** Fetch sizes for one item */
function fetch_item_sizes(string $item_id): array {
    return sb_get('item_sizes', [
        'menu_item_id' => 'eq.' . $item_id,
        'order'        => 'display_order',
    ]);
}

/** Fetch one menu item by id */
function fetch_item(string $item_id): ?array {
    $rows = sb_get('menu_items', ['id' => 'eq.' . $item_id, 'select' => 'id,name,description,category_id']);
    return $rows[0] ?? null;
}
