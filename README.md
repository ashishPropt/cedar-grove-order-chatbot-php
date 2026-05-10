# Cedar Grove Cafe & Gianni's Pizzarama

Full-stack PHP ordering app — menu browser, item chatbot, basket & checkout.

## Stack
PHP 8+ · Vanilla JS · Supabase (PostgreSQL) · No frameworks · No npm

## Structure
```
public/          <- web root (deploy this to server)
  index.php      <- menu browser
  item.php       <- item chatbot
  add_to_basket.php
  basket.php
  checkout.php
  assets/css/app.css
  assets/js/menu.js
  assets/js/chatbot.js
src/
  supabase.php   <- Supabase curl helpers
  helpers.php    <- menu fetch functions
config/
  env.php        <- NEVER committed (add manually on server)
  env.example.php
api/
  chat.php       <- legacy chatbot API
database/
  schema_and_seed.sql
```

## Setup
1. Copy `config/env.example.php` to `config/env.php` and fill in Supabase credentials
2. Run `database/schema_and_seed.sql` in Supabase SQL editor
3. Point web server document root to `public/`
4. `php -S localhost:8000 -t public/` for local dev

## Deploy
Push to `main` — GitHub Actions FTP workflow deploys automatically.
`config/env.php` is excluded from deploy — set it manually on the server.
