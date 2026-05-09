-- ==============================================================
-- Cedar Grove Cafe & Gianni's Pizzarama
-- COMPLETE PostgreSQL Schema + Full Seed Data
-- Source: scanned menu images
-- ==============================================================

BEGIN;

CREATE EXTENSION IF NOT EXISTS "pgcrypto";

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

CREATE TABLE item_sizes (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  menu_item_id  UUID NOT NULL REFERENCES menu_items(id) ON DELETE CASCADE,
  label         VARCHAR(50)  NOT NULL,
  price         NUMERIC(8,2) NOT NULL CHECK (price >= 0),
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
-- TOPPING SYSTEM
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
-- VIEWS
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
  o.id AS order_id, r.name AS restaurant, cu.name AS customer,
  o.status, o.order_type, COUNT(oi.id) AS line_items,
  o.subtotal, o.tax, o.tip, o.total, o.created_at
FROM orders o
JOIN restaurants r    ON r.id  = o.restaurant_id
LEFT JOIN customers cu ON cu.id = o.customer_id
LEFT JOIN order_items oi ON oi.order_id = o.id
GROUP BY o.id, r.name, cu.name;


-- ==============================================================
-- SEED DATA
-- All IDs are proper 8-4-4-4-12 UUID format
-- ==============================================================

-- ---------------------------------------------------------------
-- RESTAURANTS
-- ---------------------------------------------------------------
INSERT INTO restaurants (id, name, address, phone, hours, website, catering) VALUES
  ('00000000-0000-0000-0001-000000000001',
   'Cedar Grove Cafe', '160 Stelton Road, Piscataway, NJ 08854',
   '732-752-6900', 'Mon-Sat 8AM-9PM, Sundays Closed',
   'www.cedargrovecafe.com', 'cedargrovecater@optimum.net'),
  ('00000000-0000-0000-0001-000000000002',
   'Gianni''s Pizzarama', '160 Stelton Road, Piscataway, NJ 08854',
   '732-981-9507', 'Mon-Sat 8AM-9PM, Sundays Closed',
   'www.cedargrovecafe.com', 'cedargrovecater@optimum.net');

-- ---------------------------------------------------------------
-- TAGS
-- ---------------------------------------------------------------
INSERT INTO tags (name, color) VALUES
  ('Vegetarian', '#3B6D11'), ('Vegan', '#085041'),
  ('Gluten Free', '#854F0B'), ('Spicy', '#A32D2D'),
  ('Featured', '#534AB7'),   ('Kids', '#185FA5');

-- ---------------------------------------------------------------
-- CATEGORIES — Cedar Grove Cafe
-- ---------------------------------------------------------------
INSERT INTO categories (id, restaurant_id, name, display_order) VALUES
  ('00000000-0000-0001-0001-000000000001','00000000-0000-0000-0001-000000000001','Breakfast Sandwiches',    1),
  ('00000000-0000-0001-0001-000000000002','00000000-0000-0000-0001-000000000001','Fresh Egg Platters',      2),
  ('00000000-0000-0001-0001-000000000003','00000000-0000-0000-0001-000000000001','Tasty 4 Egg Omelettes',   3),
  ('00000000-0000-0001-0001-000000000004','00000000-0000-0000-0001-000000000001','Pancakes / French Toast',  4),
  ('00000000-0000-0001-0001-000000000005','00000000-0000-0000-0001-000000000001','Breakfast Sides',          5),
  ('00000000-0000-0001-0001-000000000006','00000000-0000-0000-0001-000000000001','Wings',                    6),
  ('00000000-0000-0001-0001-000000000007','00000000-0000-0000-0001-000000000001','Boneless Wings',           7),
  ('00000000-0000-0001-0001-000000000008','00000000-0000-0000-0001-000000000001','Chicken Tenders',          8),
  ('00000000-0000-0001-0001-000000000009','00000000-0000-0000-0001-000000000001','Side Orders',              9),
  ('00000000-0000-0001-0001-000000000010','00000000-0000-0000-0001-000000000001','Salads',                  10),
  ('00000000-0000-0001-0001-000000000011','00000000-0000-0000-0001-000000000001','Wraps',                   11),
  ('00000000-0000-0001-0001-000000000012','00000000-0000-0000-0001-000000000001','Appetizers',              12),
  ('00000000-0000-0001-0001-000000000013','00000000-0000-0000-0001-000000000001','Chicken Sandwiches',      13),
  ('00000000-0000-0001-0001-000000000014','00000000-0000-0000-0001-000000000001','Grilled Panini',          14),
  ('00000000-0000-0001-0001-000000000015','00000000-0000-0000-0001-000000000001','Steak Sandwiches',        15),
  ('00000000-0000-0001-0001-000000000016','00000000-0000-0000-0001-000000000001','Cold Subs',               16);

-- ---------------------------------------------------------------
-- CATEGORIES — Gianni's Pizzarama
-- ---------------------------------------------------------------
INSERT INTO categories (id, restaurant_id, name, display_order) VALUES
  ('00000000-0000-0001-0002-000000000001','00000000-0000-0000-0001-000000000002','Hot Subs',           1),
  ('00000000-0000-0001-0002-000000000002','00000000-0000-0000-0001-000000000002','Deluxe Sandwiches',  2),
  ('00000000-0000-0001-0002-000000000003','00000000-0000-0000-0001-000000000002','Pizza',              3),
  ('00000000-0000-0001-0002-000000000004','00000000-0000-0000-0001-000000000002','Gourmet Pizza',      4),
  ('00000000-0000-0001-0002-000000000005','00000000-0000-0000-0001-000000000002','Calzone & Stromboli',5),
  ('00000000-0000-0001-0002-000000000006','00000000-0000-0000-0001-000000000002','From The Grill',     6),
  ('00000000-0000-0001-0002-000000000007','00000000-0000-0000-0001-000000000002','Baked Dishes',       7),
  ('00000000-0000-0001-0002-000000000008','00000000-0000-0000-0001-000000000002','Pastas',             8),
  ('00000000-0000-0001-0002-000000000009','00000000-0000-0000-0001-000000000002','Poultry',            9),
  ('00000000-0000-0001-0002-000000000010','00000000-0000-0000-0001-000000000002','Seafood',           10);

-- ---------------------------------------------------------------
-- MODIFIER GROUPS
-- ---------------------------------------------------------------
INSERT INTO modifier_groups (id, name, ui_type, min_select, max_select) VALUES
  ('00000000-0000-0002-0000-000000000001','Bread choice',          'radio',    0, 1),
  ('00000000-0000-0002-0000-000000000002','Egg style',             'radio',    1, 1),
  ('00000000-0000-0002-0000-000000000003','Cheese type',           'radio',    0, 1),
  ('00000000-0000-0002-0000-000000000004','Add meat (optional)',   'radio',    0, 1),
  ('00000000-0000-0002-0000-000000000005','Wing sauce',            'radio',    1, 1),
  ('00000000-0000-0002-0000-000000000006','Tender style',          'radio',    1, 1),
  ('00000000-0000-0002-0000-000000000007','Dressing',              'radio',    0, 1),
  ('00000000-0000-0002-0000-000000000008','Side choice',           'radio',    0, 1),
  ('00000000-0000-0002-0000-000000000009','Extras',                'checkbox', 0, 3),
  ('00000000-0000-0002-0000-000000000010','Add protein (optional)','radio',    0, 1),
  ('00000000-0000-0002-0000-000000000011','Crust type',            'radio',    1, 1),
  ('00000000-0000-0002-0000-000000000012','Toppings',              'checkbox', 0,14),
  ('00000000-0000-0002-0000-000000000013','Extra toppings',        'checkbox', 0, 6),
  ('00000000-0000-0002-0000-000000000014','Add fillings',          'checkbox', 0, 5);

-- Bread
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('00000000-0000-0002-0000-000000000001','Hard Roll',0.00,TRUE, 1),
  ('00000000-0000-0002-0000-000000000001','Bagel',    0.50,FALSE,2),
  ('00000000-0000-0002-0000-000000000001','Wrap',     0.50,FALSE,3);

-- Egg style
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('00000000-0000-0002-0000-000000000002','Scrambled',   0,TRUE, 1),
  ('00000000-0000-0002-0000-000000000002','Over Easy',   0,FALSE,2),
  ('00000000-0000-0002-0000-000000000002','Over Medium', 0,FALSE,3),
  ('00000000-0000-0002-0000-000000000002','Over Hard',   0,FALSE,4),
  ('00000000-0000-0002-0000-000000000002','Sunny Side Up',0,FALSE,5);

-- Cheese
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000003','American',   0,1),
  ('00000000-0000-0002-0000-000000000003','Swiss',      0,2),
  ('00000000-0000-0002-0000-000000000003','Provolone',  0,3),
  ('00000000-0000-0002-0000-000000000003','Mozzarella', 0,4),
  ('00000000-0000-0002-0000-000000000003','Cheddar',    0,5),
  ('00000000-0000-0002-0000-000000000003','Jack',       0,6),
  ('00000000-0000-0002-0000-000000000003','Pepper Jack',0,7),
  ('00000000-0000-0002-0000-000000000003','Feta',       0,8),
  ('00000000-0000-0002-0000-000000000003','Gorgonzola', 0,9);

-- Meat add-on
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000004','None',     0.00,0),
  ('00000000-0000-0002-0000-000000000004','Bacon',    1.00,1),
  ('00000000-0000-0002-0000-000000000004','Ham',      1.00,2),
  ('00000000-0000-0002-0000-000000000004','Sausage',  1.00,3),
  ('00000000-0000-0002-0000-000000000004','Pork Roll',1.00,4);

-- Wing sauce
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('00000000-0000-0002-0000-000000000005','Hot', 0,FALSE,1),
  ('00000000-0000-0002-0000-000000000005','Mild',0,TRUE, 2),
  ('00000000-0000-0002-0000-000000000005','BBQ', 0,FALSE,3);

-- Tender style
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('00000000-0000-0002-0000-000000000006','Traditional',0,TRUE, 1),
  ('00000000-0000-0002-0000-000000000006','Homestyle',  0,FALSE,2),
  ('00000000-0000-0002-0000-000000000006','Crunchy',    0,FALSE,3);

-- Dressing
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000007','Italian',      0,1),
  ('00000000-0000-0002-0000-000000000007','Caesar',       0,2),
  ('00000000-0000-0002-0000-000000000007','Balsamic',     0,3),
  ('00000000-0000-0002-0000-000000000007','Ranch',        0,4),
  ('00000000-0000-0002-0000-000000000007','Oil & Vinegar',0,5),
  ('00000000-0000-0002-0000-000000000007','Honey Mustard',0,6);

-- Side
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000008','French Fries',      0,1),
  ('00000000-0000-0002-0000-000000000008','Sweet Potato Fries',0,2),
  ('00000000-0000-0002-0000-000000000008','Side Salad',        0,3),
  ('00000000-0000-0002-0000-000000000008','Coleslaw',          0,4);

-- Cold Sub extras
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000009','Extra Cheese',        1.00,1),
  ('00000000-0000-0002-0000-000000000009','Extra Meat',          2.00,2),
  ('00000000-0000-0002-0000-000000000009','Wrap instead of roll',1.00,3);

-- Protein add-on
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000010','None',       0.00,0),
  ('00000000-0000-0002-0000-000000000010','Add Chicken',3.00,1),
  ('00000000-0000-0002-0000-000000000010','Add Shrimp', 5.00,2);

-- Crust
INSERT INTO modifier_options (modifier_group_id, name, price_delta, default_selected, display_order) VALUES
  ('00000000-0000-0002-0000-000000000011','Regular',       0.00,TRUE, 1),
  ('00000000-0000-0002-0000-000000000011','Thin',          0.00,FALSE,2),
  ('00000000-0000-0002-0000-000000000011','Sicilian',      0.00,FALSE,3),
  ('00000000-0000-0002-0000-000000000011','Brooklyn Style',0.00,FALSE,4),
  ('00000000-0000-0002-0000-000000000011','Gluten Free',   3.50,FALSE,5);

-- Pizza toppings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000012','Pepperoni',   2.50, 1),
  ('00000000-0000-0002-0000-000000000012','Sausage',     2.50, 2),
  ('00000000-0000-0002-0000-000000000012','Meatball',    2.50, 3),
  ('00000000-0000-0002-0000-000000000012','Ham',         2.50, 4),
  ('00000000-0000-0002-0000-000000000012','Onions',      2.50, 5),
  ('00000000-0000-0002-0000-000000000012','Peppers',     2.50, 6),
  ('00000000-0000-0002-0000-000000000012','Mushrooms',   2.50, 7),
  ('00000000-0000-0002-0000-000000000012','Black Olives',2.50, 8),
  ('00000000-0000-0002-0000-000000000012','Spinach',     2.50, 9),
  ('00000000-0000-0002-0000-000000000012','Broccoli',    2.50,10),
  ('00000000-0000-0002-0000-000000000012','Extra Cheese',2.50,11),
  ('00000000-0000-0002-0000-000000000012','Ricotta',     2.50,12),
  ('00000000-0000-0002-0000-000000000012','Pineapple',   2.50,13),
  ('00000000-0000-0002-0000-000000000012','Anchovies',   2.50,14);

-- Gourmet pizza extra toppings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000013','Pepperoni',   3.00,1),
  ('00000000-0000-0002-0000-000000000013','Sausage',     3.00,2),
  ('00000000-0000-0002-0000-000000000013','Mushrooms',   3.00,3),
  ('00000000-0000-0002-0000-000000000013','Extra Cheese',3.00,4),
  ('00000000-0000-0002-0000-000000000013','Peppers',     3.00,5),
  ('00000000-0000-0002-0000-000000000013','Onions',      3.00,6);

-- Calzone / Stromboli fillings
INSERT INTO modifier_options (modifier_group_id, name, price_delta, display_order) VALUES
  ('00000000-0000-0002-0000-000000000014','Extra Cheese',1.50,1),
  ('00000000-0000-0002-0000-000000000014','Mushrooms',   1.50,2),
  ('00000000-0000-0002-0000-000000000014','Peppers',     1.50,3),
  ('00000000-0000-0002-0000-000000000014','Broccoli',    1.50,4),
  ('00000000-0000-0002-0000-000000000014','Onions',      1.50,5);

-- ---------------------------------------------------------------
-- TOPPING GROUPS
-- ---------------------------------------------------------------
INSERT INTO topping_groups (id, name, max_toppings) VALUES
  ('00000000-0000-0003-0000-000000000001','Pizza Toppings',    NULL),
  ('00000000-0000-0003-0000-000000000002','Calzone Fillings',  NULL),
  ('00000000-0000-0003-0000-000000000003','Stromboli Fillings',NULL);

INSERT INTO toppings (topping_group_id, name, price_personal, price_small, price_medium, price_large, price_xlarge, display_order) VALUES
  ('00000000-0000-0003-0000-000000000001','Pepperoni',     2.50,2.50,3.00,3.00,3.00, 1),
  ('00000000-0000-0003-0000-000000000001','Sausage',       2.50,2.50,3.00,3.00,3.00, 2),
  ('00000000-0000-0003-0000-000000000001','Meatball',      2.50,2.50,3.00,3.00,3.00, 3),
  ('00000000-0000-0003-0000-000000000001','Ham',           2.50,2.50,3.00,3.00,3.00, 4),
  ('00000000-0000-0003-0000-000000000001','Onions',        2.50,2.50,3.00,3.00,3.00, 5),
  ('00000000-0000-0003-0000-000000000001','Peppers',       2.50,2.50,3.00,3.00,3.00, 6),
  ('00000000-0000-0003-0000-000000000001','Mushrooms',     2.50,2.50,3.00,3.00,3.00, 7),
  ('00000000-0000-0003-0000-000000000001','Black Olives',  2.50,2.50,3.00,3.00,3.00, 8),
  ('00000000-0000-0003-0000-000000000001','Sliced Tomatoes',2.50,2.50,3.00,3.00,3.00,9),
  ('00000000-0000-0003-0000-000000000001','Broccoli',      2.50,2.50,3.00,3.00,3.00,10),
  ('00000000-0000-0003-0000-000000000001','Spinach',       2.50,2.50,3.00,3.00,3.00,11),
  ('00000000-0000-0003-0000-000000000001','Garlic',        2.50,2.50,3.00,3.00,3.00,12),
  ('00000000-0000-0003-0000-000000000001','Anchovies',     2.50,2.50,3.00,3.00,3.00,13),
  ('00000000-0000-0003-0000-000000000001','Extra Cheese',  2.50,2.50,3.00,3.00,3.00,14),
  ('00000000-0000-0003-0000-000000000001','Hot Peppers',   2.50,2.50,3.00,3.00,3.00,15),
  ('00000000-0000-0003-0000-000000000001','Ricotta',       2.50,2.50,3.00,3.00,3.00,16),
  ('00000000-0000-0003-0000-000000000001','Pineapple',     2.50,2.50,3.00,3.00,3.00,17);

INSERT INTO toppings (topping_group_id,name,price_personal,price_small,price_medium,price_large,price_xlarge,display_order)
  SELECT '00000000-0000-0003-0000-000000000002',name,price_personal,price_small,0,0,0,display_order
  FROM toppings WHERE topping_group_id='00000000-0000-0003-0000-000000000001';

INSERT INTO toppings (topping_group_id,name,price_personal,price_small,price_medium,price_large,price_xlarge,display_order)
  SELECT '00000000-0000-0003-0000-000000000003',name,price_personal,price_small,0,0,0,display_order
  FROM toppings WHERE topping_group_id='00000000-0000-0003-0000-000000000001';

-- ==============================================================
-- MENU ITEMS + SIZES
-- ==============================================================

-- ====  Breakfast Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0001-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs',1),
  ('00000000-0000-0004-0001-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs & Cheese',2),
  ('00000000-0000-0004-0001-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs, Ham & Cheese',3),
  ('00000000-0000-0004-0001-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs, Pork Roll & Cheese',4),
  ('00000000-0000-0004-0001-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs, Turkey Sausage & Cheese',5),
  ('00000000-0000-0004-0001-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs, Bacon & Cheese',6),
  ('00000000-0000-0004-0001-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Two Eggs, Turkey Bacon & Cheese',7),
  ('00000000-0000-0004-0001-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000001','Meat & Cheese',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0001-000000000001','Regular',3.75),
  ('00000000-0000-0004-0001-000000000002','Regular',4.25),
  ('00000000-0000-0004-0001-000000000003','Regular',5.25),
  ('00000000-0000-0004-0001-000000000004','Regular',5.25),
  ('00000000-0000-0004-0001-000000000005','Regular',5.25),
  ('00000000-0000-0004-0001-000000000006','Regular',5.25),
  ('00000000-0000-0004-0001-000000000007','Regular',5.25),
  ('00000000-0000-0004-0001-000000000008','Regular',6.50);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000001',1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000001';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000002',2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000001';

-- ====  Fresh Egg Platters  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0002-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs Any Style',1),
  ('00000000-0000-0004-0002-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs w/ Bacon',2),
  ('00000000-0000-0004-0002-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs w/ Ham',3),
  ('00000000-0000-0004-0002-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs w/ Turkey Bacon',4),
  ('00000000-0000-0004-0002-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs w/ Sausage',5),
  ('00000000-0000-0004-0002-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Two Eggs w/ Pork Roll',6),
  ('00000000-0000-0004-0002-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Cheese Eggs',7),
  ('00000000-0000-0004-0002-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Heartland',8),
  ('00000000-0000-0004-0002-000000000009','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000002','Veggie',9);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0002-000000000001','Regular', 5.50),
  ('00000000-0000-0004-0002-000000000002','Regular', 9.50),
  ('00000000-0000-0004-0002-000000000003','Regular', 9.50),
  ('00000000-0000-0004-0002-000000000004','Regular', 9.50),
  ('00000000-0000-0004-0002-000000000005','Regular', 9.50),
  ('00000000-0000-0004-0002-000000000006','Regular', 9.50),
  ('00000000-0000-0004-0002-000000000007','Regular',11.50),
  ('00000000-0000-0004-0002-000000000008','Regular',11.50),
  ('00000000-0000-0004-0002-000000000009','Regular',11.50);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000002',1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000002';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000003',2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000002';

-- ====  Tasty 4 Egg Omelettes  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0003-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Plain',1),
  ('00000000-0000-0004-0003-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Cheese',2),
  ('00000000-0000-0004-0003-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Cheeseburger',3),
  ('00000000-0000-0004-0003-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Veggie',4),
  ('00000000-0000-0004-0003-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Ham & Cheese',5),
  ('00000000-0000-0004-0003-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Broccoli & Cheese',6),
  ('00000000-0000-0004-0003-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Western',7),
  ('00000000-0000-0004-0003-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Bacon',8),
  ('00000000-0000-0004-0003-000000000009','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Greek',9),
  ('00000000-0000-0004-0003-000000000010','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Italian',10),
  ('00000000-0000-0004-0003-000000000011','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000003','Grandma''s',11);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0003-000000000001','Regular', 9.50),
  ('00000000-0000-0004-0003-000000000002','Regular',10.75),
  ('00000000-0000-0004-0003-000000000003','Regular',11.50),
  ('00000000-0000-0004-0003-000000000004','Regular',11.50),
  ('00000000-0000-0004-0003-000000000005','Regular',11.50),
  ('00000000-0000-0004-0003-000000000006','Regular',11.50),
  ('00000000-0000-0004-0003-000000000007','Regular',11.50),
  ('00000000-0000-0004-0003-000000000008','Regular',11.50),
  ('00000000-0000-0004-0003-000000000009','Regular',12.00),
  ('00000000-0000-0004-0003-000000000010','Regular',12.00),
  ('00000000-0000-0004-0003-000000000011','Regular',12.00);

-- ====  Pancakes / French Toast  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0004-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000004','Pancakes (Short Stack)',1),
  ('00000000-0000-0004-0004-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000004','Pancakes w/ Butter & Syrup',2),
  ('00000000-0000-0004-0004-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000004','French Toast (Short Stack)',3),
  ('00000000-0000-0004-0004-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000004','French Toast',4);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0004-000000000001','Regular',7.00),
  ('00000000-0000-0004-0004-000000000002','Regular',9.00),
  ('00000000-0000-0004-0004-000000000003','Regular',7.00),
  ('00000000-0000-0004-0004-000000000004','Regular',9.00);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000004',1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000004';

-- ====  Breakfast Sides  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0005-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000005','Side Bacon',1),
  ('00000000-0000-0004-0005-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000005','Side Sausage (Link or Patty)',2),
  ('00000000-0000-0004-0005-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000005','Side Turkey Bacon or Sausage',3),
  ('00000000-0000-0004-0005-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000005','Side Ham',4),
  ('00000000-0000-0004-0005-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000005','Side Home Fries',5);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0005-000000000001','Regular',4.00),
  ('00000000-0000-0004-0005-000000000002','Regular',4.00),
  ('00000000-0000-0004-0005-000000000003','Regular',4.25),
  ('00000000-0000-0004-0005-000000000004','Regular',4.25),
  ('00000000-0000-0004-0005-000000000005','Regular',4.25);

-- ====  Wings  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0006-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000006','Wings',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('00000000-0000-0004-0006-000000000001','6 pc',    8.00,1),
  ('00000000-0000-0004-0006-000000000001','12 pc',  12.80,2),
  ('00000000-0000-0004-0006-000000000001','24 pc',  23.00,3),
  ('00000000-0000-0004-0006-000000000001','36 pc',  35.80,4),
  ('00000000-0000-0004-0006-000000000001','50 pc',  51.20,5),
  ('00000000-0000-0004-0006-000000000001','100 pc',100.00,6);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES
  ('00000000-0000-0004-0006-000000000001','00000000-0000-0002-0000-000000000005');

-- ====  Boneless Wings  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0007-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000007','Boneless Wings',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('00000000-0000-0004-0007-000000000001','6 pc',  8.40,1),
  ('00000000-0000-0004-0007-000000000001','9 pc', 11.25,2),
  ('00000000-0000-0004-0007-000000000001','12 pc',14.50,3),
  ('00000000-0000-0004-0007-000000000001','18 pc',21.25,4);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES
  ('00000000-0000-0004-0007-000000000001','00000000-0000-0002-0000-000000000005');

-- ====  Chicken Tenders  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0008-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000008','Chicken Tenders',1);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('00000000-0000-0004-0008-000000000001','3 pc',  9.95,1),
  ('00000000-0000-0004-0008-000000000001','6 pc', 18.50,2),
  ('00000000-0000-0004-0008-000000000001','9 pc', 26.00,3),
  ('00000000-0000-0004-0008-000000000001','12 pc',33.00,4);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id) VALUES
  ('00000000-0000-0004-0008-000000000001','00000000-0000-0002-0000-000000000006');

-- ====  Side Orders  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0009-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','French Fries',1),
  ('00000000-0000-0004-0009-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','French Fries w/ Cheese',2),
  ('00000000-0000-0004-0009-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Sweet Potato Fries',3),
  ('00000000-0000-0004-0009-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Pizza Fries',4),
  ('00000000-0000-0004-0009-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Sausage',5),
  ('00000000-0000-0004-0009-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Meatballs',6),
  ('00000000-0000-0004-0009-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Steamed Broccoli',7),
  ('00000000-0000-0004-0009-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000009','Steamed Vegetables',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0009-000000000001','Regular',3.25),
  ('00000000-0000-0004-0009-000000000002','Regular',4.25),
  ('00000000-0000-0004-0009-000000000003','Regular',4.25),
  ('00000000-0000-0004-0009-000000000004','Regular',5.00),
  ('00000000-0000-0004-0009-000000000005','Regular',3.25),
  ('00000000-0000-0004-0009-000000000006','Regular',3.25),
  ('00000000-0000-0004-0009-000000000007','Regular',5.25),
  ('00000000-0000-0004-0009-000000000008','Regular',7.95);

-- ====  Salads  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0010-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Tossed Salad',1),
  ('00000000-0000-0004-0010-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Caesar Salad',2),
  ('00000000-0000-0004-0010-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Grilled Chicken Caesar Salad',3),
  ('00000000-0000-0004-0010-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Tuna Salad Platter',4),
  ('00000000-0000-0004-0010-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Antipasto Salad',5),
  ('00000000-0000-0004-0010-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Chef Salad',6),
  ('00000000-0000-0004-0010-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Gorgonzola Salad',7),
  ('00000000-0000-0004-0010-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Caprese Salad',8),
  ('00000000-0000-0004-0010-000000000009','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Cafe Salad',9),
  ('00000000-0000-0004-0010-000000000010','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000010','Bruschetta Salad',10);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0010-000000000001','Regular', 4.25),
  ('00000000-0000-0004-0010-000000000002','Regular', 4.25),
  ('00000000-0000-0004-0010-000000000003','Regular',13.25),
  ('00000000-0000-0004-0010-000000000004','Regular',13.25),
  ('00000000-0000-0004-0010-000000000005','Regular',13.50),
  ('00000000-0000-0004-0010-000000000006','Regular',13.50),
  ('00000000-0000-0004-0010-000000000007','Regular',13.95),
  ('00000000-0000-0004-0010-000000000008','Regular',13.50),
  ('00000000-0000-0004-0010-000000000009','Regular',13.50),
  ('00000000-0000-0004-0010-000000000010','Regular',13.95);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000007' FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000010';

-- ====  Wraps  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0011-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Grilled Chicken Wrap',1),
  ('00000000-0000-0004-0011-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Grilled Caesar Wrap',2),
  ('00000000-0000-0004-0011-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Cheesesteak Wrap',3),
  ('00000000-0000-0004-0011-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Turkey Club Wrap',4),
  ('00000000-0000-0004-0011-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Vegetable Wrap',5),
  ('00000000-0000-0004-0011-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Italian Wrap',6),
  ('00000000-0000-0004-0011-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Grilled Buffalo Wrap',7),
  ('00000000-0000-0004-0011-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000011','Charlie Tuna Wrap',8);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',12.50 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000011';

-- ====  Appetizers  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0012-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Mozzarella Sticks',1),
  ('00000000-0000-0004-0012-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Onion Rings',2),
  ('00000000-0000-0004-0012-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Jalapeno Poppers',3),
  ('00000000-0000-0004-0012-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Garlic Knots',4),
  ('00000000-0000-0004-0012-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Macaroni & Cheese Bites',5),
  ('00000000-0000-0004-0012-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Zucchini Sticks',6),
  ('00000000-0000-0004-0012-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Grilled Artichoke Hearts',7),
  ('00000000-0000-0004-0012-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Sliced Portobello Mushrooms',8),
  ('00000000-0000-0004-0012-000000000009','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000012','Grilled Chicken Bites',9);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0012-000000000001','Regular', 9.00),
  ('00000000-0000-0004-0012-000000000002','Regular', 5.00),
  ('00000000-0000-0004-0012-000000000003','Regular', 5.00),
  ('00000000-0000-0004-0012-000000000004','Regular', 5.00),
  ('00000000-0000-0004-0012-000000000005','Regular', 5.00),
  ('00000000-0000-0004-0012-000000000006','Regular', 5.00),
  ('00000000-0000-0004-0012-000000000007','Regular', 8.00),
  ('00000000-0000-0004-0012-000000000008','Regular', 8.00),
  ('00000000-0000-0004-0012-000000000009','Regular',12.50);

-- ====  Chicken Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0013-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Plain Chicken Steak',1),
  ('00000000-0000-0004-0013-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Chicken Cheesesteak',2),
  ('00000000-0000-0004-0013-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','California Cheesesteak',3),
  ('00000000-0000-0004-0013-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Chicken Parmigiana Hoggie',4),
  ('00000000-0000-0004-0013-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Breaded Chicken Hoggie',5),
  ('00000000-0000-0004-0013-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Grilled Chicken Hoggie',6),
  ('00000000-0000-0004-0013-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000013','Buffalo Chicken Cheesesteak Hoggie',7);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"', 10.45,1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000013';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"',12.70,2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000013';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000003' FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000013';

-- ====  Grilled Panini  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0014-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000014','Incredible Breaded Chicken',1),
  ('00000000-0000-0004-0014-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000014','Oriolino',2),
  ('00000000-0000-0004-0014-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000014','Veggie',3),
  ('00000000-0000-0004-0014-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000014','Cuban',4),
  ('00000000-0000-0004-0014-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000014','Steak',5);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',13.50 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000014';

-- ====  Steak Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0015-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','Plain Steak',1),
  ('00000000-0000-0004-0015-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','Cheesesteak',2),
  ('00000000-0000-0004-0015-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','California Cheesesteak',3),
  ('00000000-0000-0004-0015-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','California Works',4),
  ('00000000-0000-0004-0015-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','Sliced Steak Sandwich',5),
  ('00000000-0000-0004-0015-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000015','Italian Cheesesteak Hoggie',6);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"', 12.25,1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000015';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"',14.00,2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000015';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000003' FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000015';

-- ====  Cold Subs  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0016-000000000001','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#1 Ham, Salami, Capicola & Provolone',1),
  ('00000000-0000-0004-0016-000000000002','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#4 Roast Beef',2),
  ('00000000-0000-0004-0016-000000000003','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#6 Turkey',3),
  ('00000000-0000-0004-0016-000000000004','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#7 Turkey & Ham',4),
  ('00000000-0000-0004-0016-000000000005','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#14 Tuna Fish',5),
  ('00000000-0000-0004-0016-000000000006','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#15 Chicken Salad',6),
  ('00000000-0000-0004-0016-000000000007','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#16 Grilled Chicken',7),
  ('00000000-0000-0004-0016-000000000008','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#20 Prosciutto & Fresh Mozzarella',8),
  ('00000000-0000-0004-0016-000000000009','00000000-0000-0000-0001-000000000001','00000000-0000-0001-0001-000000000016','#21 Red Pepper & Mozzarella Veggie',9);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"',  9.50,1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000016';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"',10.80,2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000016';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000003',1 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000016';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000009',2 FROM menu_items WHERE category_id='00000000-0000-0001-0001-000000000016';

-- ==============================================================
-- GIANNI'S PIZZARAMA
-- ==============================================================

-- ====  Hot Subs  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0017-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Meatball Parmigiana',1),
  ('00000000-0000-0004-0017-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Sausage Parmigiana',2),
  ('00000000-0000-0004-0017-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Sausage w/ Sauce',3),
  ('00000000-0000-0004-0017-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Sausage, Peppers & Onions',4),
  ('00000000-0000-0004-0017-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Eggplant Parmigiana',5),
  ('00000000-0000-0004-0017-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Chicken Parmigiana',6),
  ('00000000-0000-0004-0017-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Veal Parmigiana',7),
  ('00000000-0000-0004-0017-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Godfather',8),
  ('00000000-0000-0004-0017-000000000009','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Roast Beef',9),
  ('00000000-0000-0004-0017-000000000010','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000001','Grilled Chicken',10);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'6"', 10.50,1 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000001';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'12"',13.80,2 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000001';

-- ====  Deluxe Sandwiches  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0018-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Reuben',1),
  ('00000000-0000-0004-0018-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Egg Salad',2),
  ('00000000-0000-0004-0018-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Hot Pastrami on Rye',3),
  ('00000000-0000-0004-0018-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','BLT',4),
  ('00000000-0000-0004-0018-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Roast Beef Club',5),
  ('00000000-0000-0004-0018-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Turkey Club',6),
  ('00000000-0000-0004-0018-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Bill''s Sloppy Joe',7),
  ('00000000-0000-0004-0018-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000002','Gyro',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0018-000000000001','Regular',12.25),
  ('00000000-0000-0004-0018-000000000002','Regular',10.00),
  ('00000000-0000-0004-0018-000000000003','Regular',12.00),
  ('00000000-0000-0004-0018-000000000004','Regular',10.00),
  ('00000000-0000-0004-0018-000000000005','Regular',12.00),
  ('00000000-0000-0004-0018-000000000006','Regular',12.00),
  ('00000000-0000-0004-0018-000000000007','Regular',12.50),
  ('00000000-0000-0004-0018-000000000008','Regular',10.25);

-- ====  Pizza  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0019-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000003','Plain Pizza',1),
  ('00000000-0000-0004-0019-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000003','Sicilian Pizza',2),
  ('00000000-0000-0004-0019-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000003','Brooklyn Style',3);

INSERT INTO item_sizes (menu_item_id, label, price, display_order) VALUES
  ('00000000-0000-0004-0019-000000000001','Personal',   11.95,1),
  ('00000000-0000-0004-0019-000000000001','Small (10")', 15.15,2),
  ('00000000-0000-0004-0019-000000000001','Medium (12")',19.00,3),
  ('00000000-0000-0004-0019-000000000001','Large (14")', 21.00,4),
  ('00000000-0000-0004-0019-000000000001','XL (16")',    21.00,5),
  ('00000000-0000-0004-0019-000000000002','Medium',      13.95,1),
  ('00000000-0000-0004-0019-000000000003','XL (16")',    21.00,1);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000011',1 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000003';
INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id, display_order)
  SELECT id,'00000000-0000-0002-0000-000000000012',2 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000003';

INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'00000000-0000-0003-0000-000000000001' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000003';

-- ====  Gourmet Pizza  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0020-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Margherita',1),
  ('00000000-0000-0004-0020-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Veggie',2),
  ('00000000-0000-0004-0020-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Grandma',3),
  ('00000000-0000-0004-0020-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','White Pizza',4),
  ('00000000-0000-0004-0020-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','BBQ Chicken',5),
  ('00000000-0000-0004-0020-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Buffalo Chicken',6),
  ('00000000-0000-0004-0020-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Hawaiian',7),
  ('00000000-0000-0004-0020-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Bruschetta',8),
  ('00000000-0000-0004-0020-000000000009','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Chicken Parmesan',9),
  ('00000000-0000-0004-0020-000000000010','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Chicken Crunch',10),
  ('00000000-0000-0004-0020-000000000011','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Philly Steak',11),
  ('00000000-0000-0004-0020-000000000012','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Artichoke Hearts',12),
  ('00000000-0000-0004-0020-000000000013','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Loaded Baked Potato',13),
  ('00000000-0000-0004-0020-000000000014','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000004','Caribbean Shrimp',14);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Personal',15.00,1 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Small',   21.00,2 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Medium',  25.00,3 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Large',   29.00,4 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000013' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';
INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'00000000-0000-0003-0000-000000000001' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000004';

-- ====  Calzone & Stromboli  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0021-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Calzone',1),
  ('00000000-0000-0004-0021-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Italian Stromboli',2),
  ('00000000-0000-0004-0021-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Chicken Stromboli',3),
  ('00000000-0000-0004-0021-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Steak Stromboli',4),
  ('00000000-0000-0004-0021-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Broccoli Stromboli',5),
  ('00000000-0000-0004-0021-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Chicken Parmigiana Stromboli',6),
  ('00000000-0000-0004-0021-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Sausage Stromboli',7),
  ('00000000-0000-0004-0021-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000005','Veggie Stromboli',8);

INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Small',12.55,1 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000005';
INSERT INTO item_sizes (menu_item_id, label, price, display_order)
  SELECT id,'Large',19.25,2 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000005';

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000014' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000005';

INSERT INTO item_toppings (menu_item_id, topping_group_id) VALUES
  ('00000000-0000-0004-0021-000000000001','00000000-0000-0003-0000-000000000002');
INSERT INTO item_toppings (menu_item_id, topping_group_id)
  SELECT id,'00000000-0000-0003-0000-000000000003'
  FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000005'
    AND id != '00000000-0000-0004-0021-000000000001';

-- ====  From The Grill  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0022-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Burger',1),
  ('00000000-0000-0004-0022-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Cheeseburger',2),
  ('00000000-0000-0004-0022-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Classic Cheeseburger',3),
  ('00000000-0000-0004-0022-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Mushroom Swiss Burger',4),
  ('00000000-0000-0004-0022-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','BC Burger',5),
  ('00000000-0000-0004-0022-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Bacon Cheeseburger',6),
  ('00000000-0000-0004-0022-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Pizza Burger',7),
  ('00000000-0000-0004-0022-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Veggie Burger',8),
  ('00000000-0000-0004-0022-000000000009','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Turkey Burger',9),
  ('00000000-0000-0004-0022-000000000010','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','Deli Burger',10),
  ('00000000-0000-0004-0022-000000000011','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000006','The Wild Burger',11);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0022-000000000001','Regular',10.00),
  ('00000000-0000-0004-0022-000000000002','Regular',10.00),
  ('00000000-0000-0004-0022-000000000003','Regular',12.95),
  ('00000000-0000-0004-0022-000000000004','Regular',13.50),
  ('00000000-0000-0004-0022-000000000005','Regular',13.50),
  ('00000000-0000-0004-0022-000000000006','Regular',13.50),
  ('00000000-0000-0004-0022-000000000007','Regular',13.50),
  ('00000000-0000-0004-0022-000000000008','Regular',12.50),
  ('00000000-0000-0004-0022-000000000009','Regular',13.50),
  ('00000000-0000-0004-0022-000000000010','Regular',14.25),
  ('00000000-0000-0004-0022-000000000011','Regular',14.25);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000008' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000006';

-- ====  Baked Dishes  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0023-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000007','Lasagna',1),
  ('00000000-0000-0004-0023-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000007','Eggplant Parmigiana',2),
  ('00000000-0000-0004-0023-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000007','Baked Ziti',3),
  ('00000000-0000-0004-0023-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000007','Stuffed Shells',4),
  ('00000000-0000-0004-0023-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000007','Baked Ravioli',5);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0023-000000000001','Regular',18.00),
  ('00000000-0000-0004-0023-000000000002','Regular',16.00),
  ('00000000-0000-0004-0023-000000000003','Regular',15.00),
  ('00000000-0000-0004-0023-000000000004','Regular',15.00),
  ('00000000-0000-0004-0023-000000000005','Regular',15.00);

-- ====  Pastas  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0024-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Linguini w/ Tomato Sauce',1),
  ('00000000-0000-0004-0024-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Penne Alla Vodka',2),
  ('00000000-0000-0004-0024-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Fettuccini Primavera',3),
  ('00000000-0000-0004-0024-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Cavatelli & Broccoli',4),
  ('00000000-0000-0004-0024-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Linguini Clam Sauce',5),
  ('00000000-0000-0004-0024-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Gnocchi Alla Sorrentina',6),
  ('00000000-0000-0004-0024-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Penne Alfredo',7),
  ('00000000-0000-0004-0024-000000000008','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000008','Spaghetti w/ Meatballs',8);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0024-000000000001','Regular',13.00),
  ('00000000-0000-0004-0024-000000000002','Regular',15.00),
  ('00000000-0000-0004-0024-000000000003','Regular',15.00),
  ('00000000-0000-0004-0024-000000000004','Regular',15.00),
  ('00000000-0000-0004-0024-000000000005','Regular',17.00),
  ('00000000-0000-0004-0024-000000000006','Regular',17.00),
  ('00000000-0000-0004-0024-000000000007','Regular',15.00),
  ('00000000-0000-0004-0024-000000000008','Regular',16.25);

INSERT INTO item_modifier_groups (menu_item_id, modifier_group_id)
  SELECT id,'00000000-0000-0002-0000-000000000010' FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000008';

-- ====  Poultry  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0025-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Francese',1),
  ('00000000-0000-0004-0025-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Marsala',2),
  ('00000000-0000-0004-0025-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Cacciatore',3),
  ('00000000-0000-0004-0025-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Piccata',4),
  ('00000000-0000-0004-0025-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Parmigiana',5),
  ('00000000-0000-0004-0025-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Saltimbocca',6),
  ('00000000-0000-0004-0025-000000000007','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000009','Chicken Contadina',7);

INSERT INTO item_sizes (menu_item_id, label, price)
  SELECT id,'Regular',18.95 FROM menu_items WHERE category_id='00000000-0000-0001-0002-000000000009';

-- ====  Seafood  ====
INSERT INTO menu_items (id, restaurant_id, category_id, name, display_order) VALUES
  ('00000000-0000-0004-0026-000000000001','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Shrimp Parmigiana',1),
  ('00000000-0000-0004-0026-000000000002','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Shrimp Francese',2),
  ('00000000-0000-0004-0026-000000000003','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Shrimp Scampi',3),
  ('00000000-0000-0004-0026-000000000004','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Tilapia Francese',4),
  ('00000000-0000-0004-0026-000000000005','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Tilapia Marsala',5),
  ('00000000-0000-0004-0026-000000000006','00000000-0000-0000-0001-000000000002','00000000-0000-0001-0002-000000000010','Seafood Combination',6);

INSERT INTO item_sizes (menu_item_id, label, price) VALUES
  ('00000000-0000-0004-0026-000000000001','Regular',21.00),
  ('00000000-0000-0004-0026-000000000002','Regular',21.00),
  ('00000000-0000-0004-0026-000000000003','Regular',21.00),
  ('00000000-0000-0004-0026-000000000004','Regular',21.00),
  ('00000000-0000-0004-0026-000000000005','Regular',21.00),
  ('00000000-0000-0004-0026-000000000006','Regular',23.00);

COMMIT;

-- ==============================================================
-- QUICK CHECKS (uncomment to verify after running)
-- ==============================================================
-- SELECT restaurant, category, COUNT(*) AS items
-- FROM v_menu GROUP BY restaurant, category ORDER BY restaurant, category;
-- SELECT COUNT(*) FROM menu_items;      -- expect 115
-- SELECT COUNT(*) FROM item_sizes;      -- expect 260+
-- SELECT COUNT(*) FROM modifier_options;-- expect 85+
-- ==============================================================
