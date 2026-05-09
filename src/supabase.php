<?php
/**
 * Supabase REST API helper
 * All data fetching goes through here.
 */
require_once __DIR__ . '/../config/env.php';

function sb_get(string $table, array $params = []): array {
    $url = SUPABASE_URL . '/rest/v1/' . $table;
    if ($params) $url .= '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: '        . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Accept: application/json',
        ],
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 300) return [];
    return json_decode($res, true) ?? [];
}

function sb_post(string $table, array $data): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $table);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'apikey: '        . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_ANON_KEY,
            'Prefer: return=representation',
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}
