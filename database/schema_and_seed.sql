-- ==============================================================
-- Cedar Grove Cafe & Gianni's Pizzarama
-- COMPLETE PostgreSQL Schema + Full Seed Data
-- Source: scanned menu images
-- ==============================================================
-- Tables:
--   Core          : restaurants, categories, menu_items
--   Pricing       : item_sizes
--   Modifiers     : modifier_groups, modifier_options, item_modifier_groups
--   Toppings      : topping_groups, toppings, item_toppings
--   Tags          : tags, item_tags
--   Ordering      : customers, orders, order_items,
--                   order_item_modifiers, order_item_toppings
-- ==============================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ---------------------------------------------------------------
-- Shared trigger: auto-update updated_at
-- ---------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN NEW.updated_at = now(); RETURN NEW; END;
$$;

-- ==============================================================
-- DROP (safe re-run)
-- ==============================================================
DROP TABLE IF EXISTS order_item_toppings   CASCADE;
DROP TABLE IF EXISTS order_item_modifiers  CASCADE;
DROP TABLE IF EXISTS order_items           CASCADE;
DROP TABLE IF EXISTS orders                CASCADE;
DROP TABLE IF EXISTS customers             CASCADE;
DROP TABLE IF EXISTS item_tags             CASCADE;
DROP TABLE IF EXISTS tags                  CASCADE;
DROP TABLE IF EXISTS item_toppings         CASCADE;
DROP TABLE IF EXISTS toppings              CASCADE;
DROP TABLE IF EXISTS topping_groups        CASCADE;
DROP TABLE IF EXISTS item_modifier_groups  CASCADE;
DROP TABLE IF EXISTS modifier_options      CASCADE;
DROP TABLE IF EXISTS modifier_groups       CASCADE;
DROP TABLE IF EXISTS item_sizes            CASCADE;
DROP TABLE IF EXISTS menu_items            CASCADE;
DROP TABLE IF EXISTS categories            CASCADE;
DROP TABLE IF EXISTS restaurants           CASCADE;

-- ==============================================================
-- CORE TABLES
-- ==============================================================

CREATE TABLE restaurants (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name        VARCHAR(100) NOT NULL,
  address     VARCHAR(200),
  phone       VARCHAR(20),
  hours       VARCHAR(200),
  website     VARCHAR(200),
  catering    VARCHAR(200),
  active      BOOLEAN NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_restaurants_upd BEFORE UPDATE ON restaurants
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE categories (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  restaurant_id UUID NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  name          VARCHAR(100) NOT NULL,
  description   TEXT,
  display_order INT NOT NULL DEFAULT 0,
  active        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_cat_restaurant ON categories(restaurant_id);

CREATE TABLE menu_items (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  restaurant_id UUID NOT NULL REFERENCES restaurants(id) ON DELETE CASCADE,
  category_id   UUID NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
  name          VARCHAR(200) NOT NULL,
  description   TEXT,
  notes         TEXT,
  available     BOOLEAN NOT NULL DEFAULT TRUE,
  featured      BOOLEAN NOT NULL DEFAULT FALSE,
  display_order INT NOT NULL DEFAULT 0,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_mi_restaurant ON menu_items(restaurant_id);
CREATE INDEX idx_mi_category   ON menu_items(category_id);
CREATE TRIGGER trg_menu_items_upd BEFORE UPDATE ON menu_items
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Prices live here, NOT on menu_items
CREATE TABLE item_sizes (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  menu_item_id UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
  label        VARCHAR(50)   NOT NULL,
  price        NUMERIC(8,2)  NOT NULL CHECK (price >= 0),
  display_order INT NOT NULL DEFAULT 0
);
CREATE INDEX idx_sizes_item ON item_sizes(menu_item_id);

-- ==============================================================
-- MODIFIER SYSTEM
-- ==============================================================
CREATE TABLE modifier_groups (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name        VARCHAR(100) NOT NULL,
  ui_type     VARCHAR(20)  NOT NULL DEFAULT 'radio'
                CHECK (ui_type IN ('radio','checkbox','dropdown')),
  min_select  INT NOT NULL DEFAULT 0,
  max_select  INT NOT NULL DEFAULT 1,
  created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE modifier_options (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  modifier_group_id UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
  name              VARCHAR(100) NOT NULL,
  price_delta       NUMERIC(6,2) NOT NULL DEFAULT 0,
  default_selected  BOOLEAN NOT NULL DEFAULT FALSE,
  display_order     INT NOT NULL DEFAULT 0
);
CREATE INDEX idx_mopt_group ON modifier_options(modifier_group_id);

CREATE TABLE item_modifier_groups (
  id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  menu_item_id      UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
  modifier_group_id UUID NOT NULL REFERENCES modifier_groups(id) ON DELETE CASCADE,
  display_order     INT NOT NULL DEFAULT 0,
  UNIQUE (menu_item_id, modifier_group_id)
);
CREATE INDEX idx_img_item  ON item_modifier_groups(menu_item_id);
CREATE INDEX idx_img_group ON item_modifier_groups(modifier_group_id);

-- ==============================================================
-- TOPPING SYSTEM (pizza / calzone / stromboli)
-- ==============================================================
CREATE TABLE topping_groups (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name         VARCHAR(100) NOT NULL,
  max_toppings INT,
  created_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE toppings (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  topping_group_id UUID NOT NULL REFERENCES topping_groups(id) ON DELETE CASCADE,
  name             VARCHAR(100) NOT NULL,
  price_personal   NUMERIC(6,2) NOT NULL DEFAULT 0,
  price_small      NUMERIC(6,2) NOT NULL DEFAULT 0,
  price_medium     NUMERIC(6,2) NOT NULL DEFAULT 0,
  price_large      NUMERIC(6,2) NOT NULL DEFAULT 0,
  price_xlarge     NUMERIC(6,2) NOT NULL DEFAULT 0,
  available        BOOLEAN NOT NULL DEFAULT TRUE,
  display_order    INT NOT NULL DEFAULT 0
);
CREATE INDEX idx_top_group ON toppings(topping_group_id);

CREATE TABLE item_toppings (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  menu_item_id     UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
  topping_group_id UUID NOT NULL REFERENCES topping_groups(id) ON DELETE CASCADE,
  UNIQUE (menu_item_id, topping_group_id)
);

-- ==============================================================
-- TAGS
-- ==============================================================
CREATE TABLE tags (
  id    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name  VARCHAR(50) NOT NULL UNIQUE,
  color VARCHAR(7)
);

CREATE TABLE item_tags (
  menu_item_id UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
  tag_id       UUID NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
  PRIMARY KEY (menu_item_id, tag_id)
);

-- ==============================================================
-- ORDERING SYSTEM
-- ==============================================================
CREATE TABLE customers (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name       VARCHAR(100),
  email      VARCHAR(200) UNIQUE,
  phone      VARCHAR(20),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE orders (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  restaurant_id UUID NOT NULL REFERENCES restaurants(id),
  customer_id   UUID REFERENCES customers(id) ON DELETE SET NULL,
  status        VARCHAR(30) NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','confirmed','preparing','ready','delivered','cancelled')),
  order_type    VARCHAR(20) NOT NULL DEFAULT 'pickup'
                  CHECK (order_type IN ('pickup','delivery','dine_in')),
  subtotal      NUMERIC(10,2) NOT NULL DEFAULT 0,
  tax           NUMERIC(10,2) NOT NULL DEFAULT 0,
  tip           NUMERIC(10,2) NOT NULL DEFAULT 0,
  total         NUMERIC(10,2) NOT NULL DEFAULT 0,
  notes         TEXT,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_orders_restaurant ON orders(restaurant_id);
CREATE INDEX idx_orders_customer   ON orders(customer_id);
CREATE INDEX idx_orders_status     ON orders(status);
CREATE TRIGGER trg_orders_upd BEFORE UPDATE ON orders
  FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE TABLE order_items (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_id     UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  menu_item_id UUID NOT NULL REFERENCES menu_items(id),
  item_size_id UUID REFERENCES item_sizes(id),
  quantity     INT NOT NULL DEFAULT 1 CHECK (quantity > 0),
  unit_price   NUMERIC(8,2) NOT NULL,
  line_total   NUMERIC(10,2) NOT NULL,
  notes        TEXT
);
CREATE INDEX idx_oi_order ON order_items(order_id);

CREATE TABLE order_item_modifiers (
  id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_item_id      UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
  modifier_option_id UUID NOT NULL REFERENCES modifier_options(id),
  price_delta        NUMERIC(6,2) NOT NULL DEFAULT 0
);

CREATE TABLE order_item_toppings (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_item_id UUID NOT NULL REFERENCES order_items(id) ON DELETE CASCADE,
  topping_id    UUID NOT NULL REFERENCES toppings(id),
  price_applied NUMERIC(6,2) NOT NULL DEFAULT 0
);

-- ==============================================================
-- USEFUL VIEWS
-- ==============================================================
CREATE OR REPLACE VIEW v_menu AS
SELECT
  r.name  AS restaurant,
  c.name  AS category,
  mi.name AS item,
  mi.description,
  s.label AS size,
  s.price,
  mi.available,
  mi.featured
FROM menu_items mi
JOIN restaurants r ON r.id = mi.restaurant_id
JOIN categories  c ON c.id = mi.category_id
JOIN item_sizes  s ON s.menu_item_id = mi.id
ORDER BY r.name, c.display_order, mi.display_order, s.display_order;

CREATE OR REPLACE VIEW v_order_summary AS
SELECT
  o.id            AS order_id,
  r.name          AS restaurant,
  cu.name         AS customer,
  o.status,
  o.order_type,
  COUNT(oi.id)    AS line_items,
  o.subtotal, o.tax, o.tip, o.total,
  o.created_at
FROM orders o
JOIN restaurants r   ON r.id  = o.restaurant_id
LEFT JOIN customers cu ON cu.id = o.customer_id
LEFT JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id, r.name, cu.name;


-- ==============================================================
-- ██████████  SEED DATA  ██████████
-- ==============================================================

-- ---------------------------------------------------------------
-- RESTAURANTS
-- ---------------------------------------------------------------
INSERT INTO restaurants (id, name, address, phone, hours, website, catering) VALUES
  ('00000000-0000-0000-0000-000000000001',
   'Cedar Grove Cafe',
   '160 Stelton Road, Piscataway, NJ 08854',
   '732-752-6900', 'Mon-Sat 8AM-9PM, Sundays Closed',
   'www.cedargrovecafe.com', 'cedargrovecater@optimum.net'),
  ('00000000-0000-0000-0000-000000000002',
   'Gianni''s Pizzarama',
   '160 Stelton Road, Piscataway, NJ 08854',
   '732-981-9507', 'Mon-Sat 8AM-9PM, Sundays Closed',
   'www.cedargrovecafe.com', 'cedargrovecater@optimum.net');

-- ---------------------------------------------------------------
-- TAGS
-- ---------------------------------------------------------------
INSERT INTO tags (name, color) VALUES
  ('Vegetarian', '#3B6D11'),
  ('Vegan',      '#085041'),
  ('Gluten Free','#854F0B'),
  ('Spicy',      '#A32D2D'),
  ('Featured',   '#534AB7'),
  ('Kids',       '#185FA5');

-- ---------------------------------------------------------------
-- CATEGORIES — Cedar Grove Cafe
-- ---------------------------------------------------------------
INSERT INTO categories (id, restaurant_id, name, display_order) VALUES
  ('c1-01','00000000-0000-0000-0000-000000000001','Breakfast Sandwiches',   1),
  ('c1-02','00000000-0000-0000-0000-000000000001','Fresh Egg Platters',     2),
  ('c1-03','00000000-0000-0000-0000-000000000001','Tasty 4 Egg Omelettes',  3),
  ('c1-04','00000000-0000-0000-0000-000000000001','Pancakes / French Toast',4),
  ('c1-05','00000000-0000-0000-0000-000000000001','Breakfast Sides',        5),
  ('c1-06','00000000-0000-0000-0000-000000000001','Wings',                  6),
  ('c1-07','00000000-0000-0000-0000-000000000001','Boneless Wings',         7),
  ('c1-08','00000000-0000-0000-0000-000000000001','Chicken Tenders',        8),
  ('c1-09','00000000-0000-0000-0000-000000000001','Side Orders',            9),
  ('c1-10','00000000-0000-0000-0000-000000000001','Salads',                10),
  ('c1-11','00000000-0000-0000-0000-000000000001','Wraps',                 11),
  ('c1-12','00000000-0000-0000-0000-000000000001','Appetizers',            12),
  ('c1-13','00000000-0000-0000-0000-000000000001','Chicken Sandwiches',    13),
  ('c1-14','00000000-0000-0000-0000-000000000001','Grilled Panini',        14),
  ('c1-15','00000000-0000-0000-0000-000000000001','Steak Sandwiches',      15),
  ('c1-16','00000000-0000-0000-0000-000000000001','Cold Subs',             16);

-- ---------------------------------------------------------------
-- CATEGORIES — Gianni's Pizzarama
-- ---------------------------------------------------------------
INSERT INTO categories (id, restaurant_id, name, display_order) VALUES
  ('c2-01','00000000-0000-0000-0000-000000000002','Hot Subs',          1),
  ('c2-02','00000000-0000-0000-0000-000000000002','Deluxe Sandwiches', 2),
  ('c2-03','00000000-0000-0000-0000-000000000002','Pizza',             3),
  ('c2-04','00000000-0000-0000-0000-000000000002','Gourmet Pizza',     4),
  ('c2-05','00000000-0000-0000-0000-000000000002','Calzone & Stromboli',5),
  ('c2-06','00000000-0000-0000-0000-000000000002','From The Grill',    6),
  ('c2-07','00000000-0000-0000-0000-000000000002','Baked Dishes',      7),
  ('c2-08','00000000-0000-0000-0000-000000000002','Pastas',            8),
  ('c2-09','00000000-0000-0000-0000-000000000002','Poultry',           9),
  ('c2-10','00000000-0000-0000-0000-000000000002','Seafood',          10);

-- ---------------------------------------------------------------
-- MODIFIER GROUPS
-- ---------------------------------------------------------------
INSERT INTO modifier_groups (id, name, ui_type, min_select, max_select) VALUES
  ('mg-bread',    'Bread choice',         'radio',    0, 1),
  ('mg-egg',      'Egg style',            'radio',    1, 1),
  ('mg-cheese',   'Cheese type',          'radio',    0, 1),
  ('mg-meat',     'Add meat (optional)',  'radio',    0, 1),
  ('mg-wsauce',   'Wing sauce',           'radio',    1, 1),
  ('mg-tender',   'Tender style',         'radio',    1, 1),
  ('mg-dressing', 'Dressing',             'radio',    0, 1),
  ('mg-side',     'Side choice',          'radio',    0, 1),
  ('mg-size-cs',  'Size',                 'radio',    1, 1),
  ('mg-size-ss',  'Size',                 'radio',    1, 1),
  ('mg-size-csub','Size',                 'radio',    1, 1),
  ('mg-size-hs',  'Size',                 'radio',    1, 1),
  ('mg-size-cz',  'Size',                 'radio',    1, 1),
  ('mg-extras',   'Extras',               'checkbox', 0, 3),
  ('mg-protein',  'Add protein (optional)','radio',   0, 1),
  ('mg-crust',    'Crust type',           'radio',    1, 1),
  ('mg-pizza-top','Toppings',             'checkbox', 0,14),
  ('mg-gp-top',   'Extra toppings',       'checkbox', 0, 6),
  ('mg-cz-fill',  'Add fillings',         'checkbox', 0, 5);

-- Modifier options — Bread
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('mg-bread','Hard Roll', 0.00, TRUE,  1),
  ('mg-bread','Bagel',     0.50, FALSE, 2),
  ('mg-bread','Wrap',      0.50, FALSE, 3);

-- Modifier options — Egg style
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('mg-egg','Scrambled',   0, TRUE,  1),
  ('mg-egg','Over Easy',   0, FALSE, 2),
  ('mg-egg','Over Medium', 0, FALSE, 3),
  ('mg-egg','Over Hard',   0, FALSE, 4),
  ('mg-egg','Sunny Side Up',0,FALSE, 5);

-- Modifier options — Cheese
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-cheese','American',   0, 1),
  ('mg-cheese','Swiss',      0, 2),
  ('mg-cheese','Provolone',  0, 3),
  ('mg-cheese','Mozzarella', 0, 4),
  ('mg-cheese','Cheddar',    0, 5),
  ('mg-cheese','Jack',       0, 6),
  ('mg-cheese','Pepper Jack',0, 7),
  ('mg-cheese','Feta',       0, 8),
  ('mg-cheese','Gorgonzola', 0, 9);

-- Modifier options — Meat add-on
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-meat','None',          0.00, 0),
  ('mg-meat','Bacon',         1.00, 1),
  ('mg-meat','Ham',           1.00, 2),
  ('mg-meat','Sausage',       1.00, 3),
  ('mg-meat','Pork Roll',     1.00, 4);

-- Modifier options — Wing sauce
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('mg-wsauce','Hot',  0, FALSE, 1),
  ('mg-wsauce','Mild', 0, TRUE,  2),
  ('mg-wsauce','BBQ',  0, FALSE, 3);

-- Modifier options — Tender style
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('mg-tender','Traditional', 0, TRUE,  1),
  ('mg-tender','Homestyle',   0, FALSE, 2),
  ('mg-tender','Crunchy',     0, FALSE, 3);

-- Modifier options — Dressing
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-dressing','Italian',       0, 1),
  ('mg-dressing','Caesar',        0, 2),
  ('mg-dressing','Balsamic',      0, 3),
  ('mg-dressing','Ranch',         0, 4),
  ('mg-dressing','Oil & Vinegar', 0, 5),
  ('mg-dressing','Honey Mustard', 0, 6);

-- Modifier options — Side
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-side','French Fries',       0, 1),
  ('mg-side','Sweet Potato Fries', 0, 2),
  ('mg-side','Side Salad',         0, 3),
  ('mg-side','Coleslaw',           0, 4);

-- Modifier options — Chicken Sandwich size
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-size-cs','6"',  10.45, 1),
  ('mg-size-cs','12"', 12.70, 2);

-- Modifier options — Steak Sandwich size
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-size-ss','6"',  12.25, 1),
  ('mg-size-ss','12"', 14.00, 2);

-- Modifier options — Cold Sub size
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-size-csub','6"',   9.50, 1),
  ('mg-size-csub','12"', 10.80, 2);

-- Modifier options — Hot Sub size
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-size-hs','6"',  10.50, 1),
  ('mg-size-hs','12"', 13.80, 2);

-- Modifier options — Calzone/Stromboli size
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-size-cz','Small', 12.55, 1),
  ('mg-size-cz','Large', 19.25, 2);

-- Modifier options — Cold Sub extras
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-extras','Extra Cheese',       1.00, 1),
  ('mg-extras','Extra Meat',         2.00, 2),
  ('mg-extras','Wrap instead of roll',1.00,3);

-- Modifier options — Pasta protein
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-protein','None',        0.00, 0),
  ('mg-protein','Add Chicken', 3.00, 1),
  ('mg-protein','Add Shrimp',  5.00, 2);

-- Modifier options — Crust
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('mg-crust','Regular',        0.00, TRUE,  1),
  ('mg-crust','Thin',           0.00, FALSE, 2),
  ('mg-crust','Sicilian',       0.00, FALSE, 3),
  ('mg-crust','Brooklyn Style', 0.00, FALSE, 4),
  ('mg-crust','Gluten Free',    3.50, FALSE, 5);

-- Modifier options — Pizza toppings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-pizza-top','Pepperoni',   2.50,  1),
  ('mg-pizza-top','Sausage',     2.50,  2),
  ('mg-pizza-top','Meatball',    2.50,  3),
  ('mg-pizza-top','Ham',         2.50,  4),
  ('mg-pizza-top','Onions',      2.50,  5),
  ('mg-pizza-top','Peppers',     2.50,  6),
  ('mg-pizza-top','Mushrooms',   2.50,  7),
  ('mg-pizza-top','Black Olives',2.50,  8),
  ('mg-pizza-top','Spinach',     2.50,  9),
  ('mg-pizza-top','Broccoli',    2.50, 10),
  ('mg-pizza-top','Extra Cheese',2.50, 11),
  ('mg-pizza-top','Ricotta',     2.50, 12),
  ('mg-pizza-top','Pineapple',   2.50, 13),
  ('mg-pizza-top','Anchovies',   2.50, 14);

-- Modifier options — Gourmet pizza extra toppings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-gp-top','Pepperoni',   3.00, 1),
  ('mg-gp-top','Sausage',     3.00, 2),
  ('mg-gp-top','Mushrooms',   3.00, 3),
  ('mg-gp-top','Extra Cheese',3.00, 4),
  ('mg-gp-top','Peppers',     3.00, 5),
  ('mg-gp-top','Onions',      3.00, 6);

-- Modifier options — Calzone/Stromboli fillings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('mg-cz-fill','Extra Cheese',1.50, 1),
  ('mg-cz-fill','Mushrooms',   1.50, 2),
  ('mg-cz-fill','Peppers',     1.50, 3),
  ('mg-cz-fill','Broccoli',    1.50, 4),
  ('mg-cz-fill','Onions',      1.50, 5);

-- ---------------------------------------------------------------
-- TOPPING GROUPS (structural pizza toppings with per-size pricing)
-- ---------------------------------------------------------------
INSERT INTO topping_groups (id, name, max_toppings) VALUES
  ('tg-pizza',    'Pizza Toppings',     NULL),
  ('tg-calzone',  'Calzone Fillings',   NULL),
  ('tg-stromboli','Stromboli Fillings', NULL);

INSERT INTO toppings (topping_group_id, name, price_personal, price_small, price_medium, price_large, price_xlarge, display_order) VALUES
  ('tg-pizza','Pepperoni',   2.50,2.50,3.00,3.00,3.00, 1),
  ('tg-pizza','Sausage',     2.50,2.50,3.00,3.00,3.00, 2),
  ('tg-pizza','Meatball',    2.50,2.50,3.00,3.00,3.00, 3),
  ('tg-pizza','Ham',         2.50,2.50,3.00,3.00,3.00, 4),
  ('tg-pizza','Onions',      2.50,2.50,3.00,3.00,3.00, 5),
  ('tg-pizza','Peppers',     2.50,2.50,3.00,3.00,3.00, 6),
  ('tg-pizza','Mushrooms',   2.50,2.50,3.00,3.00,3.00, 7),
  ('tg-pizza','Black Olives',2.50,2.50,3.00,3.00,3.00, 8),
  ('tg-pizza','Sliced Tomatoes',2.50,2.50,3.00,3.00,3.00,9),
  ('tg-pizza','Broccoli',    2.50,2.50,3.00,3.00,3.00,10),
  ('tg-pizza','Spinach',     2.50,2.50,3.00,3.00,3.00,11),
  ('tg-pizza','Garlic',      2.50,2.50,3.00,3.00,3.00,12),
  ('tg-pizza','Anchovies',   2.50,2.50,3.00,3.00,3.00,13),
  ('tg-pizza','Extra Cheese',2.50,2.50,3.00,3.00,3.00,14),
  ('tg-pizza','Hot Peppers', 2.50,2.50,3.00,3.00,3.00,15),
  ('tg-pizza','Ricotta',     2.50,2.50,3.00,3.00,3.00,16),
  ('tg-pizza','Pineapple',   2.50,2.50,3.00,3.00,3.00,17);

INSERT INTO toppings (topping_group_id, name, price_personal, price_small, price_medium, price_large, price_xlarge, display_order)
  SELECT 'tg-calzone', name, price_personal, price_small, 0, 0, 0, display_order FROM toppings WHERE topping_group_id='tg-pizza';

INSERT INTO toppings (topping_group_id, name, price_personal, price_small, price_medium, price_large, price_xlarge, display_order)
  SELECT 'tg-stromboli', name, price_personal, price_small, 0, 0, 0, display_order FROM toppings WHERE topping_group_id='tg-pizza';


-- ==============================================================
-- MENU ITEMS + SIZES
-- Helper pattern:
--   INSERT menu_item -> capture id via CTE -> INSERT item_sizes
-- We use fixed short UUIDs for items so item_modifier_groups
-- and item_toppings can reference them cleanly.
-- ==============================================================

-- ---------------------------------------------------------------
-- CEDAR GROVE CAFE
-- ---------------------------------------------------------------

-- ====  Breakfast Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-bs-01','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs',1),
  ('mi-bs-02','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs & Cheese',2),
  ('mi-bs-03','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs, Ham & Cheese',3),
  ('mi-bs-04','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs, Pork Roll & Cheese',4),
  ('mi-bs-05','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs, Turkey Sausage & Cheese',5),
  ('mi-bs-06','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs, Bacon & Cheese',6),
  ('mi-bs-07','00000000-0000-0000-0000-000000000001','c1-01','Two Eggs, Turkey Bacon & Cheese',7),
  ('mi-bs-08','00000000-0000-0000-0000-000000000001','c1-01','Meat & Cheese',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-bs-01','Regular', 3.75),
  ('mi-bs-02','Regular', 4.25),
  ('mi-bs-03','Regular', 5.25),
  ('mi-bs-04','Regular', 5.25),
  ('mi-bs-05','Regular', 5.25),
  ('mi-bs-06','Regular', 5.25),
  ('mi-bs-07','Regular', 5.25),
  ('mi-bs-08','Regular', 6.50);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id, 'mg-bread', 1 FROM menu_items WHERE category_id='c1-01';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id, 'mg-egg',   2 FROM menu_items WHERE category_id='c1-01';

-- ====  Fresh Egg Platters  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-ep-01','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs Any Style',1),
  ('mi-ep-02','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs w/ Bacon',2),
  ('mi-ep-03','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs w/ Ham',3),
  ('mi-ep-04','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs w/ Turkey Bacon',4),
  ('mi-ep-05','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs w/ Sausage',5),
  ('mi-ep-06','00000000-0000-0000-0000-000000000001','c1-02','Two Eggs w/ Pork Roll',6),
  ('mi-ep-07','00000000-0000-0000-0000-000000000001','c1-02','Cheese Eggs',7),
  ('mi-ep-08','00000000-0000-0000-0000-000000000001','c1-02','Heartland',8),
  ('mi-ep-09','00000000-0000-0000-0000-000000000001','c1-02','Veggie',9);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-ep-01','Regular', 5.50),
  ('mi-ep-02','Regular', 9.50),
  ('mi-ep-03','Regular', 9.50),
  ('mi-ep-04','Regular', 9.50),
  ('mi-ep-05','Regular', 9.50),
  ('mi-ep-06','Regular', 9.50),
  ('mi-ep-07','Regular',11.50),
  ('mi-ep-08','Regular',11.50),
  ('mi-ep-09','Regular',11.50);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-egg',   1 FROM menu_items WHERE category_id='c1-02';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-cheese',2 FROM menu_items WHERE category_id='c1-02';

-- ====  Tasty 4 Egg Omelettes  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-om-01','00000000-0000-0000-0000-000000000001','c1-03','Plain',1),
  ('mi-om-02','00000000-0000-0000-0000-000000000001','c1-03','Cheese',2),
  ('mi-om-03','00000000-0000-0000-0000-000000000001','c1-03','Cheeseburger',3),
  ('mi-om-04','00000000-0000-0000-0000-000000000001','c1-03','Veggie',4),
  ('mi-om-05','00000000-0000-0000-0000-000000000001','c1-03','Ham & Cheese',5),
  ('mi-om-06','00000000-0000-0000-0000-000000000001','c1-03','Broccoli & Cheese',6),
  ('mi-om-07','00000000-0000-0000-0000-000000000001','c1-03','Western',7),
  ('mi-om-08','00000000-0000-0000-0000-000000000001','c1-03','Bacon',8),
  ('mi-om-09','00000000-0000-0000-0000-000000000001','c1-03','Greek',9),
  ('mi-om-10','00000000-0000-0000-0000-000000000001','c1-03','Italian',10),
  ('mi-om-11','00000000-0000-0000-0000-000000000001','c1-03','Grandma''s',11);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-om-01','Regular', 9.50),
  ('mi-om-02','Regular',10.75),
  ('mi-om-03','Regular',11.50),
  ('mi-om-04','Regular',11.50),
  ('mi-om-05','Regular',11.50),
  ('mi-om-06','Regular',11.50),
  ('mi-om-07','Regular',11.50),
  ('mi-om-08','Regular',11.50),
  ('mi-om-09','Regular',12.00),
  ('mi-om-10','Regular',12.00),
  ('mi-om-11','Regular',12.00);

-- ====  Pancakes / French Toast  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-pf-01','00000000-0000-0000-0000-000000000001','c1-04','Pancakes (Short Stack)',1),
  ('mi-pf-02','00000000-0000-0000-0000-000000000001','c1-04','Pancakes w/ Butter & Syrup',2),
  ('mi-pf-03','00000000-0000-0000-0000-000000000001','c1-04','French Toast (Short Stack)',3),
  ('mi-pf-04','00000000-0000-0000-0000-000000000001','c1-04','French Toast',4);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-pf-01','Regular',7.00),
  ('mi-pf-02','Regular',9.00),
  ('mi-pf-03','Regular',7.00),
  ('mi-pf-04','Regular',9.00);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-meat',1 FROM menu_items WHERE category_id='c1-04';

-- ====  Breakfast Sides  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-si-01','00000000-0000-0000-0000-000000000001','c1-05','Side Bacon',1),
  ('mi-si-02','00000000-0000-0000-0000-000000000001','c1-05','Side Sausage (Link or Patty)',2),
  ('mi-si-03','00000000-0000-0000-0000-000000000001','c1-05','Side Turkey Bacon or Sausage',3),
  ('mi-si-04','00000000-0000-0000-0000-000000000001','c1-05','Side Ham',4),
  ('mi-si-05','00000000-0000-0000-0000-000000000001','c1-05','Side Home Fries',5);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-si-01','Regular',4.00),
  ('mi-si-02','Regular',4.00),
  ('mi-si-03','Regular',4.25),
  ('mi-si-04','Regular',4.25),
  ('mi-si-05','Regular',4.25);

-- ====  Wings  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-wg-01','00000000-0000-0000-0000-000000000001','c1-06','Wings',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('mi-wg-01','6 pc',    8.00, 1),
  ('mi-wg-01','12 pc',  12.80, 2),
  ('mi-wg-01','24 pc',  23.00, 3),
  ('mi-wg-01','36 pc',  35.80, 4),
  ('mi-wg-01','50 pc',  51.20, 5),
  ('mi-wg-01','100 pc',100.00, 6);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES ('mi-wg-01','mg-wsauce');

-- ====  Boneless Wings  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-bw-01','00000000-0000-0000-0000-000000000001','c1-07','Boneless Wings',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('mi-bw-01','6 pc',  8.40, 1),
  ('mi-bw-01','9 pc', 11.25, 2),
  ('mi-bw-01','12 pc',14.50, 3),
  ('mi-bw-01','18 pc',21.25, 4);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES ('mi-bw-01','mg-wsauce');

-- ====  Chicken Tenders  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-ct-01','00000000-0000-0000-0000-000000000001','c1-08','Chicken Tenders',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('mi-ct-01','3 pc',  9.95, 1),
  ('mi-ct-01','6 pc', 18.50, 2),
  ('mi-ct-01','9 pc', 26.00, 3),
  ('mi-ct-01','12 pc',33.00, 4);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES ('mi-ct-01','mg-tender');

-- ====  Side Orders  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-so-01','00000000-0000-0000-0000-000000000001','c1-09','French Fries',1),
  ('mi-so-02','00000000-0000-0000-0000-000000000001','c1-09','French Fries w/ Cheese',2),
  ('mi-so-03','00000000-0000-0000-0000-000000000001','c1-09','Sweet Potato Fries',3),
  ('mi-so-04','00000000-0000-0000-0000-000000000001','c1-09','Pizza Fries',4),
  ('mi-so-05','00000000-0000-0000-0000-000000000001','c1-09','Sausage',5),
  ('mi-so-06','00000000-0000-0000-0000-000000000001','c1-09','Meatballs',6),
  ('mi-so-07','00000000-0000-0000-0000-000000000001','c1-09','Steamed Broccoli',7),
  ('mi-so-08','00000000-0000-0000-0000-000000000001','c1-09','Steamed Vegetables',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-so-01','Regular',3.25),
  ('mi-so-02','Regular',4.25),
  ('mi-so-03','Regular',4.25),
  ('mi-so-04','Regular',5.00),
  ('mi-so-05','Regular',3.25),
  ('mi-so-06','Regular',3.25),
  ('mi-so-07','Regular',5.25),
  ('mi-so-08','Regular',7.95);

-- ====  Salads  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-sa-01','00000000-0000-0000-0000-000000000001','c1-10','Tossed Salad',1),
  ('mi-sa-02','00000000-0000-0000-0000-000000000001','c1-10','Caesar Salad',2),
  ('mi-sa-03','00000000-0000-0000-0000-000000000001','c1-10','Grilled Chicken Caesar Salad',3),
  ('mi-sa-04','00000000-0000-0000-0000-000000000001','c1-10','Tuna Salad Platter',4),
  ('mi-sa-05','00000000-0000-0000-0000-000000000001','c1-10','Antipasto Salad',5),
  ('mi-sa-06','00000000-0000-0000-0000-000000000001','c1-10','Chef Salad',6),
  ('mi-sa-07','00000000-0000-0000-0000-000000000001','c1-10','Gorgonzola Salad',7),
  ('mi-sa-08','00000000-0000-0000-0000-000000000001','c1-10','Caprese Salad',8),
  ('mi-sa-09','00000000-0000-0000-0000-000000000001','c1-10','Café Salad',9),
  ('mi-sa-10','00000000-0000-0000-0000-000000000001','c1-10','Bruschetta Salad',10);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-sa-01','Regular', 4.25),
  ('mi-sa-02','Regular', 4.25),
  ('mi-sa-03','Regular',13.25),
  ('mi-sa-04','Regular',13.25),
  ('mi-sa-05','Regular',13.50),
  ('mi-sa-06','Regular',13.50),
  ('mi-sa-07','Regular',13.95),
  ('mi-sa-08','Regular',13.50),
  ('mi-sa-09','Regular',13.50),
  ('mi-sa-10','Regular',13.95);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'mg-dressing' FROM menu_items WHERE category_id='c1-10';

-- ====  Wraps  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-wr-01','00000000-0000-0000-0000-000000000001','c1-11','Grilled Chicken Wrap',1),
  ('mi-wr-02','00000000-0000-0000-0000-000000000001','c1-11','Grilled Caesar Wrap',2),
  ('mi-wr-03','00000000-0000-0000-0000-000000000001','c1-11','Cheesesteak Wrap',3),
  ('mi-wr-04','00000000-0000-0000-0000-000000000001','c1-11','Turkey Club Wrap',4),
  ('mi-wr-05','00000000-0000-0000-0000-000000000001','c1-11','Vegetable Wrap',5),
  ('mi-wr-06','00000000-0000-0000-0000-000000000001','c1-11','Italian Wrap',6),
  ('mi-wr-07','00000000-0000-0000-0000-000000000001','c1-11','Grilled Buffalo Wrap',7),
  ('mi-wr-08','00000000-0000-0000-0000-000000000001','c1-11','Charlie Tuna Wrap',8);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',12.50 FROM menu_items WHERE category_id='c1-11';

-- ====  Appetizers  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-ap-01','00000000-0000-0000-0000-000000000001','c1-12','Mozzarella Sticks',1),
  ('mi-ap-02','00000000-0000-0000-0000-000000000001','c1-12','Onion Rings',2),
  ('mi-ap-03','00000000-0000-0000-0000-000000000001','c1-12','Jalapeño Poppers',3),
  ('mi-ap-04','00000000-0000-0000-0000-000000000001','c1-12','Garlic Knots',4),
  ('mi-ap-05','00000000-0000-0000-0000-000000000001','c1-12','Macaroni & Cheese Bites',5),
  ('mi-ap-06','00000000-0000-0000-0000-000000000001','c1-12','Zucchini Sticks',6),
  ('mi-ap-07','00000000-0000-0000-0000-000000000001','c1-12','Grilled Artichoke Hearts',7),
  ('mi-ap-08','00000000-0000-0000-0000-000000000001','c1-12','Sliced Portobello Mushrooms',8),
  ('mi-ap-09','00000000-0000-0000-0000-000000000001','c1-12','Grilled Chicken Bites',9);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-ap-01','Regular', 9.00),
  ('mi-ap-02','Regular', 5.00),
  ('mi-ap-03','Regular', 5.00),
  ('mi-ap-04','Regular', 5.00),
  ('mi-ap-05','Regular', 5.00),
  ('mi-ap-06','Regular', 5.00),
  ('mi-ap-07','Regular', 8.00),
  ('mi-ap-08','Regular', 8.00),
  ('mi-ap-09','Regular',12.50);

-- ====  Chicken Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-cs-01','00000000-0000-0000-0000-000000000001','c1-13','Plain Chicken Steak',1),
  ('mi-cs-02','00000000-0000-0000-0000-000000000001','c1-13','Chicken Cheesesteak',2),
  ('mi-cs-03','00000000-0000-0000-0000-000000000001','c1-13','California Cheesesteak',3),
  ('mi-cs-04','00000000-0000-0000-0000-000000000001','c1-13','Chicken Parmigiana Hoggie',4),
  ('mi-cs-05','00000000-0000-0000-0000-000000000001','c1-13','Breaded Chicken Hoggie',5),
  ('mi-cs-06','00000000-0000-0000-0000-000000000001','c1-13','Grilled Chicken Hoggie',6),
  ('mi-cs-07','00000000-0000-0000-0000-000000000001','c1-13','Buffalo Chicken Cheesesteak Hoggie',7);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id, '6"',  10.45, 1 FROM menu_items WHERE category_id='c1-13';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id, '12"', 12.70, 2 FROM menu_items WHERE category_id='c1-13';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-cheese',1 FROM menu_items WHERE category_id='c1-13';

-- ====  Grilled Panini  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-gp-01','00000000-0000-0000-0000-000000000001','c1-14','Incredible Breaded Chicken',1),
  ('mi-gp-02','00000000-0000-0000-0000-000000000001','c1-14','Oriolino',2),
  ('mi-gp-03','00000000-0000-0000-0000-000000000001','c1-14','Veggie',3),
  ('mi-gp-04','00000000-0000-0000-0000-000000000001','c1-14','Cuban',4),
  ('mi-gp-05','00000000-0000-0000-0000-000000000001','c1-14','Steak',5);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',13.50 FROM menu_items WHERE category_id='c1-14';

-- ====  Steak Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-ss-01','00000000-0000-0000-0000-000000000001','c1-15','Plain Steak',1),
  ('mi-ss-02','00000000-0000-0000-0000-000000000001','c1-15','Cheesesteak',2),
  ('mi-ss-03','00000000-0000-0000-0000-000000000001','c1-15','California Cheesesteak',3),
  ('mi-ss-04','00000000-0000-0000-0000-000000000001','c1-15','California Works',4),
  ('mi-ss-05','00000000-0000-0000-0000-000000000001','c1-15','Sliced Steak Sandwich',5),
  ('mi-ss-06','00000000-0000-0000-0000-000000000001','c1-15','Italian Cheesesteak Hoggie',6);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"',  12.25, 1 FROM menu_items WHERE category_id='c1-15';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"', 14.00, 2 FROM menu_items WHERE category_id='c1-15';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-cheese',1 FROM menu_items WHERE category_id='c1-15';

-- ====  Cold Subs  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-cb-01','00000000-0000-0000-0000-000000000001','c1-16','#1 Ham, Salami, Capicola & Provolone',1),
  ('mi-cb-02','00000000-0000-0000-0000-000000000001','c1-16','#4 Roast Beef',2),
  ('mi-cb-03','00000000-0000-0000-0000-000000000001','c1-16','#6 Turkey',3),
  ('mi-cb-04','00000000-0000-0000-0000-000000000001','c1-16','#7 Turkey & Ham',4),
  ('mi-cb-05','00000000-0000-0000-0000-000000000001','c1-16','#14 Tuna Fish',5),
  ('mi-cb-06','00000000-0000-0000-0000-000000000001','c1-16','#15 Chicken Salad',6),
  ('mi-cb-07','00000000-0000-0000-0000-000000000001','c1-16','#16 Grilled Chicken',7),
  ('mi-cb-08','00000000-0000-0000-0000-000000000001','c1-16','#20 Prosciutto & Fresh Mozzarella',8),
  ('mi-cb-09','00000000-0000-0000-0000-000000000001','c1-16','#21 Red Pepper & Mozzarella Veggie',9);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id, '6"',  9.50, 1 FROM menu_items WHERE category_id='c1-16';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id, '12"',10.80, 2 FROM menu_items WHERE category_id='c1-16';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-cheese',1 FROM menu_items WHERE category_id='c1-16';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-extras',2 FROM menu_items WHERE category_id='c1-16';


-- ---------------------------------------------------------------
-- GIANNI'S PIZZARAMA
-- ---------------------------------------------------------------

-- ====  Hot Subs  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-hs-01','00000000-0000-0000-0000-000000000002','c2-01','Meatball Parmigiana',1),
  ('mi-hs-02','00000000-0000-0000-0000-000000000002','c2-01','Sausage Parmigiana',2),
  ('mi-hs-03','00000000-0000-0000-0000-000000000002','c2-01','Sausage w/ Sauce',3),
  ('mi-hs-04','00000000-0000-0000-0000-000000000002','c2-01','Sausage, Peppers & Onions',4),
  ('mi-hs-05','00000000-0000-0000-0000-000000000002','c2-01','Eggplant Parmigiana',5),
  ('mi-hs-06','00000000-0000-0000-0000-000000000002','c2-01','Chicken Parmigiana',6),
  ('mi-hs-07','00000000-0000-0000-0000-000000000002','c2-01','Veal Parmigiana',7),
  ('mi-hs-08','00000000-0000-0000-0000-000000000002','c2-01','Godfather',8),
  ('mi-hs-09','00000000-0000-0000-0000-000000000002','c2-01','Roast Beef',9),
  ('mi-hs-10','00000000-0000-0000-0000-000000000002','c2-01','Grilled Chicken',10);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"',  10.50, 1 FROM menu_items WHERE category_id='c2-01';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"', 13.80, 2 FROM menu_items WHERE category_id='c2-01';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'mg-size-hs' FROM menu_items WHERE category_id='c2-01';

-- ====  Deluxe Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-ds-01','00000000-0000-0000-0000-000000000002','c2-02','Reuben',1),
  ('mi-ds-02','00000000-0000-0000-0000-000000000002','c2-02','Egg Salad',2),
  ('mi-ds-03','00000000-0000-0000-0000-000000000002','c2-02','Hot Pastrami on Rye',3),
  ('mi-ds-04','00000000-0000-0000-0000-000000000002','c2-02','BLT',4),
  ('mi-ds-05','00000000-0000-0000-0000-000000000002','c2-02','Roast Beef Club',5),
  ('mi-ds-06','00000000-0000-0000-0000-000000000002','c2-02','Turkey Club',6),
  ('mi-ds-07','00000000-0000-0000-0000-000000000002','c2-02','Bill''s Sloppy Joe',7),
  ('mi-ds-08','00000000-0000-0000-0000-000000000002','c2-02','Gyro',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-ds-01','Regular',12.25),
  ('mi-ds-02','Regular',10.00),
  ('mi-ds-03','Regular',12.00),
  ('mi-ds-04','Regular',10.00),
  ('mi-ds-05','Regular',12.00),
  ('mi-ds-06','Regular',12.00),
  ('mi-ds-07','Regular',12.50),
  ('mi-ds-08','Regular',10.25);

-- ====  Pizza  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-pz-01','00000000-0000-0000-0000-000000000002','c2-03','Plain Pizza',1),
  ('mi-pz-02','00000000-0000-0000-0000-000000000002','c2-03','Sicilian Pizza',2),
  ('mi-pz-03','00000000-0000-0000-0000-000000000002','c2-03','Brooklyn Style',3);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('mi-pz-01','Personal', 11.95, 1),
  ('mi-pz-01','Small (10")',15.15,2),
  ('mi-pz-01','Medium (12")',19.00,3),
  ('mi-pz-01','Large (14")',21.00,4),
  ('mi-pz-01','XL (16")',   21.00,5),
  ('mi-pz-02','Medium',     13.95,1),
  ('mi-pz-03','XL (16")',   21.00,1);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-crust',    1 FROM menu_items WHERE category_id='c2-03';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-pizza-top',2 FROM menu_items WHERE category_id='c2-03';

INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'tg-pizza' FROM menu_items WHERE category_id='c2-03';

-- ====  Gourmet Pizza  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-gm-01','00000000-0000-0000-0000-000000000002','c2-04','Margherita',1),
  ('mi-gm-02','00000000-0000-0000-0000-000000000002','c2-04','Veggie',2),
  ('mi-gm-03','00000000-0000-0000-0000-000000000002','c2-04','Grandma',3),
  ('mi-gm-04','00000000-0000-0000-0000-000000000002','c2-04','White Pizza',4),
  ('mi-gm-05','00000000-0000-0000-0000-000000000002','c2-04','BBQ Chicken',5),
  ('mi-gm-06','00000000-0000-0000-0000-000000000002','c2-04','Buffalo Chicken',6),
  ('mi-gm-07','00000000-0000-0000-0000-000000000002','c2-04','Hawaiian',7),
  ('mi-gm-08','00000000-0000-0000-0000-000000000002','c2-04','Bruschetta',8),
  ('mi-gm-09','00000000-0000-0000-0000-000000000002','c2-04','Chicken Parmesan',9),
  ('mi-gm-10','00000000-0000-0000-0000-000000000002','c2-04','Chicken Crunch',10),
  ('mi-gm-11','00000000-0000-0000-0000-000000000002','c2-04','Philly Steak',11),
  ('mi-gm-12','00000000-0000-0000-0000-000000000002','c2-04','Artichoke Hearts',12),
  ('mi-gm-13','00000000-0000-0000-0000-000000000002','c2-04','Loaded Baked Potato',13),
  ('mi-gm-14','00000000-0000-0000-0000-000000000002','c2-04','Caribbean Shrimp',14);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Personal',15.00,1 FROM menu_items WHERE category_id='c2-04';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Small',   21.00,2 FROM menu_items WHERE category_id='c2-04';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Medium',  25.00,3 FROM menu_items WHERE category_id='c2-04';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Large',   29.00,4 FROM menu_items WHERE category_id='c2-04';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'mg-gp-top' FROM menu_items WHERE category_id='c2-04';

INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'tg-pizza' FROM menu_items WHERE category_id='c2-04';

-- ====  Calzone & Stromboli  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-cz-01','00000000-0000-0000-0000-000000000002','c2-05','Calzone',1),
  ('mi-cz-02','00000000-0000-0000-0000-000000000002','c2-05','Italian Stromboli',2),
  ('mi-cz-03','00000000-0000-0000-0000-000000000002','c2-05','Chicken Stromboli',3),
  ('mi-cz-04','00000000-0000-0000-0000-000000000002','c2-05','Steak Stromboli',4),
  ('mi-cz-05','00000000-0000-0000-0000-000000000002','c2-05','Broccoli Stromboli',5),
  ('mi-cz-06','00000000-0000-0000-0000-000000000002','c2-05','Chicken Parmigiana Stromboli',6),
  ('mi-cz-07','00000000-0000-0000-0000-000000000002','c2-05','Sausage Stromboli',7),
  ('mi-cz-08','00000000-0000-0000-0000-000000000002','c2-05','Veggie Stromboli',8);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Small',12.55,1 FROM menu_items WHERE category_id='c2-05';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Large',19.25,2 FROM menu_items WHERE category_id='c2-05';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'mg-cz-fill',1 FROM menu_items WHERE category_id='c2-05';

INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'tg-calzone' FROM menu_items WHERE id LIKE 'mi-cz-01';
INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'tg-stromboli' FROM menu_items WHERE category_id='c2-05' AND id != 'mi-cz-01';

-- ====  From The Grill  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-fg-01','00000000-0000-0000-0000-000000000002','c2-06','Burger',1),
  ('mi-fg-02','00000000-0000-0000-0000-000000000002','c2-06','Cheeseburger',2),
  ('mi-fg-03','00000000-0000-0000-0000-000000000002','c2-06','Classic Cheeseburger',3),
  ('mi-fg-04','00000000-0000-0000-0000-000000000002','c2-06','Mushroom Swiss Burger',4),
  ('mi-fg-05','00000000-0000-0000-0000-000000000002','c2-06','BC Burger',5),
  ('mi-fg-06','00000000-0000-0000-0000-000000000002','c2-06','Bacon Cheeseburger',6),
  ('mi-fg-07','00000000-0000-0000-0000-000000000002','c2-06','Pizza Burger',7),
  ('mi-fg-08','00000000-0000-0000-0000-000000000002','c2-06','Veggie Burger',8),
  ('mi-fg-09','00000000-0000-0000-0000-000000000002','c2-06','Turkey Burger',9),
  ('mi-fg-10','00000000-0000-0000-0000-000000000002','c2-06','Deli Burger',10),
  ('mi-fg-11','00000000-0000-0000-0000-000000000002','c2-06','The Wild Burger',11);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-fg-01','Regular',10.00),
  ('mi-fg-02','Regular',10.00),
  ('mi-fg-03','Regular',12.95),
  ('mi-fg-04','Regular',13.50),
  ('mi-fg-05','Regular',13.50),
  ('mi-fg-06','Regular',13.50),
  ('mi-fg-07','Regular',13.50),
  ('mi-fg-08','Regular',12.50),
  ('mi-fg-09','Regular',13.50),
  ('mi-fg-10','Regular',14.25),
  ('mi-fg-11','Regular',14.25);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'mg-side' FROM menu_items WHERE category_id='c2-06';

-- ====  Baked Dishes  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-bd-01','00000000-0000-0000-0000-000000000002','c2-07','Lasagna',1),
  ('mi-bd-02','00000000-0000-0000-0000-000000000002','c2-07','Eggplant Parmigiana',2),
  ('mi-bd-03','00000000-0000-0000-0000-000000000002','c2-07','Baked Ziti',3),
  ('mi-bd-04','00000000-0000-0000-0000-000000000002','c2-07','Stuffed Shells',4),
  ('mi-bd-05','00000000-0000-0000-0000-000000000002','c2-07','Baked Ravioli',5);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-bd-01','Regular',18.00),
  ('mi-bd-02','Regular',16.00),
  ('mi-bd-03','Regular',15.00),
  ('mi-bd-04','Regular',15.00),
  ('mi-bd-05','Regular',15.00);

-- ====  Pastas  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-pa-01','00000000-0000-0000-0000-000000000002','c2-08','Linguini w/ Tomato Sauce',1),
  ('mi-pa-02','00000000-0000-0000-0000-000000000002','c2-08','Penne Alla Vodka',2),
  ('mi-pa-03','00000000-0000-0000-0000-000000000002','c2-08','Fettuccini Primavera',3),
  ('mi-pa-04','00000000-0000-0000-0000-000000000002','c2-08','Cavatelli & Broccoli',4),
  ('mi-pa-05','00000000-0000-0000-0000-000000000002','c2-08','Linguini Clam Sauce',5),
  ('mi-pa-06','00000000-0000-0000-0000-000000000002','c2-08','Gnocchi Alla Sorrentina',6),
  ('mi-pa-07','00000000-0000-0000-0000-000000000002','c2-08','Penne Alfredo',7),
  ('mi-pa-08','00000000-0000-0000-0000-000000000002','c2-08','Spaghetti w/ Meatballs',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-pa-01','Regular',13.00),
  ('mi-pa-02','Regular',15.00),
  ('mi-pa-03','Regular',15.00),
  ('mi-pa-04','Regular',15.00),
  ('mi-pa-05','Regular',17.00),
  ('mi-pa-06','Regular',17.00),
  ('mi-pa-07','Regular',15.00),
  ('mi-pa-08','Regular',16.25);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'mg-protein' FROM menu_items WHERE category_id='c2-08';

-- ====  Poultry  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-po-01','00000000-0000-0000-0000-000000000002','c2-09','Chicken Francese',1),
  ('mi-po-02','00000000-0000-0000-0000-000000000002','c2-09','Chicken Marsala',2),
  ('mi-po-03','00000000-0000-0000-0000-000000000002','c2-09','Chicken Cacciatore',3),
  ('mi-po-04','00000000-0000-0000-0000-000000000002','c2-09','Chicken Piccata',4),
  ('mi-po-05','00000000-0000-0000-0000-000000000002','c2-09','Chicken Parmigiana',5),
  ('mi-po-06','00000000-0000-0000-0000-000000000002','c2-09','Chicken Saltimbocca',6),
  ('mi-po-07','00000000-0000-0000-0000-000000000002','c2-09','Chicken Contadina',7);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',18.95 FROM menu_items WHERE category_id='c2-09';

-- ====  Seafood  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('mi-sf-01','00000000-0000-0000-0000-000000000002','c2-10','Shrimp Parmigiana',1),
  ('mi-sf-02','00000000-0000-0000-0000-000000000002','c2-10','Shrimp Francese',2),
  ('mi-sf-03','00000000-0000-0000-0000-000000000002','c2-10','Shrimp Scampi',3),
  ('mi-sf-04','00000000-0000-0000-0000-000000000002','c2-10','Tilapia Francese',4),
  ('mi-sf-05','00000000-0000-0000-0000-000000000002','c2-10','Tilapia Marsala',5),
  ('mi-sf-06','00000000-0000-0000-0000-000000000002','c2-10','Seafood Combination',6);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('mi-sf-01','Regular',21.00),
  ('mi-sf-02','Regular',21.00),
  ('mi-sf-03','Regular',21.00),
  ('mi-sf-04','Regular',21.00),
  ('mi-sf-05','Regular',21.00),
  ('mi-sf-06','Regular',23.00);

COMMIT;

-- ==============================================================
-- QUICK CHECKS
-- ==============================================================
-- SELECT restaurant, category, COUNT(*) AS items
-- FROM v_menu GROUP BY restaurant, category ORDER BY restaurant, category;
--
-- SELECT COUNT(*) FROM menu_items;   -- expect ~115
-- SELECT COUNT(*) FROM item_sizes;   -- expect ~250+
-- SELECT COUNT(*) FROM modifier_options; -- expect ~80+
-- ==============================================================
