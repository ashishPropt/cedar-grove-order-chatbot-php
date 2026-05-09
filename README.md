# Cedar Grove Order Chatbot — PHP Version (POC)

PHP + vanilla JS chatbot for ordering from **Cedar Grove Cafe** and **Gianni's Pizzarama**.

## Architecture

```
┌─────────────────────────┐        POST /api/chat.php
│  public/index.html      │  ◄──►  { action, ...params }
│  public/assets/js/      │        returns JSON
│    chatbot.js           │
│  public/assets/css/     │
│    chatbot.css          │
└─────────────────────────┘
         ↕
┌─────────────────────────┐
│  api/chat.php           │  Stateless JSON API
│  src/menu_data.php      │  All menu data + price helpers
└─────────────────────────┘
```

**State lives in the browser** — the PHP API is fully stateless. Each step is a single `POST` with an `action` key. Cart is persisted in `sessionStorage`.

## API actions

| action | params | returns |
|--------|--------|---------|
| `get_restaurants` | — | `{ restaurants: [] }` |
| `get_categories` | `restaurant` | `{ categories: [] }` |
| `get_items` | `category` | `{ items: [] }` |
| `get_sizes` | `category, item` | `{ sizes: [], price_map: {} }` |
| `get_modifiers` | `category` | `{ modifiers: {} }` |
| `price_item` | `category, item, size_key, selections` | `{ name, base_price, mod_total, mod_lines, line_total }` |

## File structure

```
api/
  chat.php               ← Stateless JSON API (6 actions)
src/
  menu_data.php          ← Full menu: 250+ items, all modifiers & prices
public/
  index.html             ← Single-page shell
  assets/
    css/chatbot.css      ← All styles
    js/chatbot.js        ← State machine + API calls (vanilla JS)
```

## Local setup

Requires PHP 8.0+ and a web server. Quickest way:

```bash
cd public
php -S localhost:8000
```

Open `http://localhost:8000` — the JS calls `../api/chat.php` which resolves correctly from the `public/` document root via the server routing below.

### Apache / Nginx

Point document root to `public/`. Add a rewrite so `/api/chat.php` resolves to `../api/chat.php`:

**Apache** (`.htaccess` in `public/`):
```apache
RewriteEngine On
RewriteRule ^api/(.*)$ ../api/$1 [L]
```

**Nginx**:
```nginx
root /var/www/cedar-grove/public;
location /api/ {
    alias /var/www/cedar-grove/api/;
    try_files $uri =404;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $request_filename;
}
```

## Supabase / DB integration (next step)

Add a `save_order` action to `api/chat.php` that POSTs to Supabase REST:
```php
case 'save_order':
    $url = getenv('SUPABASE_URL') . '/rest/v1/orders';
    $key = getenv('SUPABASE_ANON_KEY');
    // ... curl POST
```

## Tech

PHP 8+ · Vanilla JS (no framework) · Plain CSS · Zero npm dependencies
