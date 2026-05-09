<?php
// Cedar Grove Cafe & Gianni's Pizzarama — Full menu data

function get_menu(): array {
    return [
        'restaurants' => ['Cedar Grove Cafe', "Gianni's Pizzarama"],

        'categories' => [
            'Cedar Grove Cafe' => [
                'Breakfast Sandwiches', 'Fresh Egg Platters', 'Tasty 4 Egg Omelettes',
                'Pancakes / French Toast', 'Breakfast Sides', 'Wings', 'Boneless Wings',
                'Chicken Tenders', 'Side Orders', 'Salads', 'Wraps', 'Appetizers',
                'Chicken Sandwiches', 'Grilled Panini', 'Steak Sandwiches', 'Cold Subs',
            ],
            "Gianni's Pizzarama" => [
                'Hot Subs', 'Deluxe Sandwiches', 'Pizza', 'Gourmet Pizza',
                'Calzone & Stromboli', 'From The Grill', 'Baked Dishes',
                'Pastas', 'Poultry', 'Seafood',
            ],
        ],

        'items' => [

            // ---- Cedar Grove Cafe ----

            'Breakfast Sandwiches' => [
                'items' => ['Two Eggs', 'Two Eggs & Cheese', 'Two Eggs, Ham & Cheese',
                    'Two Eggs, Pork Roll & Cheese', 'Two Eggs, Turkey Sausage & Cheese',
                    'Two Eggs, Bacon & Cheese', 'Two Eggs, Turkey Bacon & Cheese', 'Meat & Cheese'],
                'sizes' => [
                    'Two Eggs' => 3.75, 'Two Eggs & Cheese' => 4.25,
                    'Two Eggs, Ham & Cheese' => 5.25, 'Two Eggs, Pork Roll & Cheese' => 5.25,
                    'Two Eggs, Turkey Sausage & Cheese' => 5.25, 'Two Eggs, Bacon & Cheese' => 5.25,
                    'Two Eggs, Turkey Bacon & Cheese' => 5.25, 'Meat & Cheese' => 6.50,
                ],
                'modifiers' => [
                    'bread' => ['type' => 'radio', 'label' => 'Bread choice',
                        'options' => [['Hard Roll', 0], ['Bagel', 0.50], ['Wrap', 0.50]]],
                    'egg'   => ['type' => 'radio', 'label' => 'Egg style',
                        'options' => [['Scrambled', 0], ['Over Easy', 0], ['Over Medium', 0], ['Over Hard', 0], ['Sunny Side Up', 0]]],
                ],
            ],

            'Fresh Egg Platters' => [
                'items' => ['Two Eggs Any Style', 'Two Eggs w/ Bacon', 'Two Eggs w/ Ham',
                    'Two Eggs w/ Turkey Bacon', 'Two Eggs w/ Sausage', 'Two Eggs w/ Pork Roll',
                    'Cheese Eggs', 'Heartland', 'Veggie'],
                'sizes' => [
                    'Two Eggs Any Style' => 5.50, 'Two Eggs w/ Bacon' => 9.50,
                    'Two Eggs w/ Ham' => 9.50, 'Two Eggs w/ Turkey Bacon' => 9.50,
                    'Two Eggs w/ Sausage' => 9.50, 'Two Eggs w/ Pork Roll' => 9.50,
                    'Cheese Eggs' => 11.50, 'Heartland' => 11.50, 'Veggie' => 11.50,
                ],
                'modifiers' => [
                    'egg'    => ['type' => 'radio', 'label' => 'Egg style',
                        'options' => [['Scrambled', 0], ['Over Easy', 0], ['Over Medium', 0], ['Over Hard', 0], ['Sunny Side Up', 0]]],
                    'cheese' => ['type' => 'radio', 'label' => 'Cheese type',
                        'options' => [['American', 0], ['Swiss', 0], ['Provolone', 0], ['Cheddar', 0], ['Pepper Jack', 0]]],
                ],
            ],

            'Tasty 4 Egg Omelettes' => [
                'items' => ['Plain', 'Cheese', 'Cheeseburger', 'Veggie', 'Ham & Cheese',
                    'Broccoli & Cheese', 'Western', 'Bacon', 'Greek', 'Italian', "Grandma's"],
                'sizes' => [
                    'Plain' => 9.50, 'Cheese' => 10.75, 'Cheeseburger' => 11.50, 'Veggie' => 11.50,
                    'Ham & Cheese' => 11.50, 'Broccoli & Cheese' => 11.50, 'Western' => 11.50,
                    'Bacon' => 11.50, 'Greek' => 12.00, 'Italian' => 12.00, "Grandma's" => 12.00,
                ],
                'modifiers' => [],
            ],

            'Pancakes / French Toast' => [
                'items' => ['Pancakes (Short Stack)', 'Pancakes w/ Butter & Syrup',
                    'French Toast (Short Stack)', 'French Toast'],
                'sizes' => [
                    'Pancakes (Short Stack)' => 7.00, 'Pancakes w/ Butter & Syrup' => 9.00,
                    'French Toast (Short Stack)' => 7.00, 'French Toast' => 9.00,
                ],
                'modifiers' => [
                    'meat' => ['type' => 'radio', 'label' => 'Add meat (optional)',
                        'options' => [['None', 0], ['Bacon', 1.00], ['Ham', 1.00], ['Sausage', 1.00], ['Pork Roll', 1.00]]],
                ],
            ],

            'Breakfast Sides' => [
                'items' => ['Side Bacon', 'Side Sausage (Link or Patty)',
                    'Side Turkey Bacon or Sausage', 'Side Ham', 'Side Home Fries'],
                'sizes' => [
                    'Side Bacon' => 4.00, 'Side Sausage (Link or Patty)' => 4.00,
                    'Side Turkey Bacon or Sausage' => 4.25, 'Side Ham' => 4.25, 'Side Home Fries' => 4.25,
                ],
                'modifiers' => [],
            ],

            'Wings' => [
                'items' => ['6 Wings', '12 Wings', '24 Wings', '36 Wings', '50 Wings', '100 Wings'],
                'sizes' => [
                    '6 Wings' => 8.00, '12 Wings' => 12.80, '24 Wings' => 23.00,
                    '36 Wings' => 35.80, '50 Wings' => 51.20, '100 Wings' => 100.00,
                ],
                'modifiers' => [
                    'sauce' => ['type' => 'radio', 'label' => 'Wing sauce',
                        'options' => [['Hot', 0], ['Mild', 0], ['BBQ', 0]]],
                ],
            ],

            'Boneless Wings' => [
                'items' => ['6 Boneless Wings', '9 Boneless Wings', '12 Boneless Wings', '18 Boneless Wings'],
                'sizes' => [
                    '6 Boneless Wings' => 8.40, '9 Boneless Wings' => 11.25,
                    '12 Boneless Wings' => 14.50, '18 Boneless Wings' => 21.25,
                ],
                'modifiers' => [
                    'sauce' => ['type' => 'radio', 'label' => 'Wing sauce',
                        'options' => [['Hot', 0], ['Mild', 0], ['BBQ', 0]]],
                ],
            ],

            'Chicken Tenders' => [
                'items' => ['3 Tenders', '6 Tenders', '9 Tenders', '12 Tenders'],
                'sizes' => ['3 Tenders' => 9.95, '6 Tenders' => 18.50, '9 Tenders' => 26.00, '12 Tenders' => 33.00],
                'modifiers' => [
                    'style' => ['type' => 'radio', 'label' => 'Tender style',
                        'options' => [['Traditional', 0], ['Homestyle', 0], ['Crunchy', 0]]],
                ],
            ],

            'Side Orders' => [
                'items' => ['French Fries', 'French Fries w/ Cheese', 'Sweet Potato Fries',
                    'Pizza Fries', 'Sausage', 'Meatballs', 'Steamed Broccoli', 'Steamed Vegetables'],
                'sizes' => [
                    'French Fries' => 3.25, 'French Fries w/ Cheese' => 4.25,
                    'Sweet Potato Fries' => 4.25, 'Pizza Fries' => 5.00,
                    'Sausage' => 3.25, 'Meatballs' => 3.25,
                    'Steamed Broccoli' => 5.25, 'Steamed Vegetables' => 7.95,
                ],
                'modifiers' => [],
            ],

            'Salads' => [
                'items' => ['Tossed Salad', 'Caesar Salad', 'Grilled Chicken Caesar Salad',
                    'Tuna Salad Platter', 'Antipasto Salad', 'Chef Salad',
                    'Gorgonzola Salad', 'Caprese Salad', 'Café Salad', 'Bruschetta Salad'],
                'sizes' => [
                    'Tossed Salad' => 4.25, 'Caesar Salad' => 4.25,
                    'Grilled Chicken Caesar Salad' => 13.25, 'Tuna Salad Platter' => 13.25,
                    'Antipasto Salad' => 13.50, 'Chef Salad' => 13.50,
                    'Gorgonzola Salad' => 13.95, 'Caprese Salad' => 13.50,
                    'Café Salad' => 13.50, 'Bruschetta Salad' => 13.95,
                ],
                'modifiers' => [
                    'dressing' => ['type' => 'radio', 'label' => 'Dressing',
                        'options' => [['Italian', 0], ['Caesar', 0], ['Balsamic', 0], ['Ranch', 0], ['Oil & Vinegar', 0], ['Honey Mustard', 0]]],
                ],
            ],

            'Wraps' => [
                'items' => ['Grilled Chicken Wrap', 'Grilled Caesar Wrap', 'Cheesesteak Wrap',
                    'Turkey Club Wrap', 'Vegetable Wrap', 'Italian Wrap',
                    'Grilled Buffalo Wrap', 'Charlie Tuna Wrap'],
                'price' => 12.50,
                'modifiers' => [],
            ],

            'Appetizers' => [
                'items' => ['Mozzarella Sticks', 'Onion Rings', 'Jalapeño Poppers',
                    'Garlic Knots', 'Macaroni & Cheese Bites', 'Zucchini Sticks',
                    'Grilled Artichoke Hearts', 'Sliced Portobello Mushrooms', 'Grilled Chicken Bites'],
                'sizes' => [
                    'Mozzarella Sticks' => 9.00, 'Onion Rings' => 5.00, 'Jalapeño Poppers' => 5.00,
                    'Garlic Knots' => 5.00, 'Macaroni & Cheese Bites' => 5.00,
                    'Zucchini Sticks' => 5.00, 'Grilled Artichoke Hearts' => 8.00,
                    'Sliced Portobello Mushrooms' => 8.00, 'Grilled Chicken Bites' => 12.50,
                ],
                'modifiers' => [],
            ],

            'Chicken Sandwiches' => [
                'items' => ['Plain Chicken Steak', 'Chicken Cheesesteak', 'California Cheesesteak',
                    'Chicken Parmigiana Hoggie', 'Breaded Chicken Hoggie',
                    'Grilled Chicken Hoggie', 'Buffalo Chicken Cheesesteak Hoggie'],
                'modifiers' => [
                    'size'   => ['type' => 'radio', 'label' => 'Size',
                        'options' => [['6"', 10.45], ['12"', 12.70]]],
                    'cheese' => ['type' => 'radio', 'label' => 'Cheese',
                        'options' => [['American', 0], ['Swiss', 0], ['Provolone', 0], ['Mozzarella', 0]]],
                ],
            ],

            'Grilled Panini' => [
                'items' => ['Incredible Breaded Chicken', 'Oriolino', 'Veggie', 'Cuban', 'Steak'],
                'price' => 13.50,
                'modifiers' => [],
            ],

            'Steak Sandwiches' => [
                'items' => ['Plain Steak', 'Cheesesteak', 'California Cheesesteak',
                    'California Works', 'Sliced Steak Sandwich', 'Italian Cheesesteak Hoggie'],
                'modifiers' => [
                    'size'   => ['type' => 'radio', 'label' => 'Size',
                        'options' => [['6"', 12.25], ['12"', 14.00]]],
                    'cheese' => ['type' => 'radio', 'label' => 'Cheese',
                        'options' => [['American', 0], ['Swiss', 0], ['Provolone', 0], ['Mozzarella', 0]]],
                ],
            ],

            'Cold Subs' => [
                'items' => ['#1 Ham, Salami, Capicola & Provolone', '#4 Roast Beef', '#6 Turkey',
                    '#7 Turkey & Ham', '#14 Tuna Fish', '#15 Chicken Salad',
                    '#16 Grilled Chicken', '#20 Prosciutto & Fresh Mozzarella', '#21 Red Pepper & Mozzarella Veggie'],
                'modifiers' => [
                    'size'   => ['type' => 'radio', 'label' => 'Size',
                        'options' => [['6"', 9.50], ['12"', 10.80]]],
                    'cheese' => ['type' => 'radio', 'label' => 'Cheese',
                        'options' => [['American', 0], ['Swiss', 0], ['Provolone', 0], ['Mozzarella', 0]]],
                    'extras' => ['type' => 'checkbox', 'label' => 'Extras',
                        'options' => [['Extra Cheese', 1.00], ['Extra Meat', 2.00], ['Wrap instead of roll', 1.00]]],
                ],
            ],

            // ---- Gianni's Pizzarama ----

            'Pizza' => [
                'items' => ['Plain Pizza', 'Sicilian Pizza', 'Brooklyn Style'],
                'pizza_sizes' => [
                    'Plain Pizza'    => ['Personal' => 11.95, 'Small (10")' => 15.15, 'Medium (12")' => 19.00, 'Large (14")' => 21.00, 'XL (16")' => 21.00],
                    'Sicilian Pizza' => ['Medium' => 13.95],
                    'Brooklyn Style' => ['XL (16")' => 21.00],
                ],
                'modifiers' => [
                    'crust'    => ['type' => 'radio', 'label' => 'Crust',
                        'options' => [['Regular', 0], ['Thin', 0], ['Gluten Free', 3.50]]],
                    'toppings' => ['type' => 'checkbox', 'label' => 'Toppings (each +$2.50)',
                        'options' => [
                            ['Pepperoni', 2.50], ['Sausage', 2.50], ['Meatball', 2.50], ['Ham', 2.50],
                            ['Onions', 2.50], ['Peppers', 2.50], ['Mushrooms', 2.50], ['Black Olives', 2.50],
                            ['Spinach', 2.50], ['Broccoli', 2.50], ['Extra Cheese', 2.50],
                            ['Ricotta', 2.50], ['Pineapple', 2.50], ['Anchovies', 2.50],
                        ]],
                ],
            ],

            'Gourmet Pizza' => [
                'items' => ['Margherita', 'Veggie', 'Grandma', 'White Pizza', 'BBQ Chicken',
                    'Buffalo Chicken', 'Hawaiian', 'Bruschetta', 'Chicken Parmesan',
                    'Chicken Crunch', 'Philly Steak', 'Artichoke Hearts', 'Loaded Baked Potato', 'Caribbean Shrimp'],
                'gourmet_sizes' => ['Personal' => 15.00, 'Small' => 21.00, 'Medium' => 25.00, 'Large' => 29.00],
                'modifiers' => [
                    'toppings' => ['type' => 'checkbox', 'label' => 'Extra toppings (each +$3.00)',
                        'options' => [['Pepperoni', 3.00], ['Sausage', 3.00], ['Mushrooms', 3.00], ['Extra Cheese', 3.00], ['Peppers', 3.00], ['Onions', 3.00]]],
                ],
            ],

            'Calzone & Stromboli' => [
                'items' => ['Calzone', 'Italian Stromboli', 'Chicken Stromboli', 'Steak Stromboli',
                    'Broccoli Stromboli', 'Chicken Parmigiana Stromboli', 'Sausage Stromboli', 'Veggie Stromboli'],
                'modifiers' => [
                    'size'     => ['type' => 'radio', 'label' => 'Size',
                        'options' => [['Small', 12.55], ['Large', 19.25]]],
                    'toppings' => ['type' => 'checkbox', 'label' => 'Add fillings (each +$1.50)',
                        'options' => [['Extra Cheese', 1.50], ['Mushrooms', 1.50], ['Peppers', 1.50], ['Broccoli', 1.50], ['Onions', 1.50]]],
                ],
            ],

            'From The Grill' => [
                'items' => ['Burger', 'Cheeseburger', 'Classic Cheeseburger', 'Mushroom Swiss Burger',
                    'BC Burger', 'Bacon Cheeseburger', 'Pizza Burger', 'Veggie Burger',
                    'Turkey Burger', 'Deli Burger', 'The Wild Burger'],
                'sizes' => [
                    'Burger' => 10.00, 'Cheeseburger' => 10.00, 'Classic Cheeseburger' => 12.95,
                    'Mushroom Swiss Burger' => 13.50, 'BC Burger' => 13.50, 'Bacon Cheeseburger' => 13.50,
                    'Pizza Burger' => 13.50, 'Veggie Burger' => 12.50, 'Turkey Burger' => 13.50,
                    'Deli Burger' => 14.25, 'The Wild Burger' => 14.25,
                ],
                'modifiers' => [
                    'side' => ['type' => 'radio', 'label' => 'Side',
                        'options' => [['French Fries', 0], ['Sweet Potato Fries', 0], ['Side Salad', 0], ['Coleslaw', 0]]],
                ],
            ],

            'Baked Dishes' => [
                'items' => ['Lasagna', 'Eggplant Parmigiana', 'Baked Ziti', 'Stuffed Shells', 'Baked Ravioli'],
                'sizes' => [
                    'Lasagna' => 18.00, 'Eggplant Parmigiana' => 16.00,
                    'Baked Ziti' => 15.00, 'Stuffed Shells' => 15.00, 'Baked Ravioli' => 15.00,
                ],
                'modifiers' => [],
            ],

            'Pastas' => [
                'items' => ['Linguini w/ Tomato Sauce', 'Penne Alla Vodka', 'Fettuccini Primavera',
                    'Cavatelli & Broccoli', 'Linguini Clam Sauce', 'Gnocchi Alla Sorrentina',
                    'Penne Alfredo', 'Spaghetti w/ Meatballs'],
                'sizes' => [
                    'Linguini w/ Tomato Sauce' => 13.00, 'Penne Alla Vodka' => 15.00,
                    'Fettuccini Primavera' => 15.00, 'Cavatelli & Broccoli' => 15.00,
                    'Linguini Clam Sauce' => 17.00, 'Gnocchi Alla Sorrentina' => 17.00,
                    'Penne Alfredo' => 15.00, 'Spaghetti w/ Meatballs' => 16.25,
                ],
                'modifiers' => [
                    'protein' => ['type' => 'radio', 'label' => 'Add protein (optional)',
                        'options' => [['None', 0], ['Add Chicken', 3.00], ['Add Shrimp', 5.00]]],
                ],
            ],

            'Poultry' => [
                'items' => ['Chicken Francese', 'Chicken Marsala', 'Chicken Cacciatore',
                    'Chicken Piccata', 'Chicken Parmigiana', 'Chicken Saltimbocca', 'Chicken Contadina'],
                'price' => 18.95,
                'modifiers' => [],
            ],

            'Seafood' => [
                'items' => ['Shrimp Parmigiana', 'Shrimp Francese', 'Shrimp Scampi',
                    'Tilapia Francese', 'Tilapia Marsala', 'Seafood Combination'],
                'sizes' => [
                    'Shrimp Parmigiana' => 21.00, 'Shrimp Francese' => 21.00,
                    'Shrimp Scampi' => 21.00, 'Tilapia Francese' => 21.00,
                    'Tilapia Marsala' => 21.00, 'Seafood Combination' => 23.00,
                ],
                'modifiers' => [],
            ],

            'Hot Subs' => [
                'items' => ['Meatball Parmigiana', 'Sausage Parmigiana', 'Sausage w/ Sauce',
                    'Sausage, Peppers & Onions', 'Eggplant Parmigiana', 'Chicken Parmigiana',
                    'Veal Parmigiana', 'Godfather', 'Roast Beef', 'Grilled Chicken'],
                'modifiers' => [
                    'size' => ['type' => 'radio', 'label' => 'Size',
                        'options' => [['6"', 10.50], ['12"', 13.80]]],
                ],
            ],

            'Deluxe Sandwiches' => [
                'items' => ['Reuben', 'Egg Salad', 'Hot Pastrami on Rye', 'BLT',
                    'Roast Beef Club', 'Turkey Club', "Bill's Sloppy Joe", 'Gyro'],
                'sizes' => [
                    'Reuben' => 12.25, 'Egg Salad' => 10.00, 'Hot Pastrami on Rye' => 12.00,
                    'BLT' => 10.00, 'Roast Beef Club' => 12.00, 'Turkey Club' => 12.00,
                    "Bill's Sloppy Joe" => 12.50, 'Gyro' => 10.25,
                ],
                'modifiers' => [],
            ],
        ],
    ];
}

function get_base_price(string $category, string $item, ?string $size_key): float {
    $menu = get_menu();
    $data = $menu['items'][$category] ?? null;
    if (!$data) return 0.0;
    if (isset($data['price'])) return (float) $data['price'];
    if (isset($data['gourmet_sizes'])) return (float) ($data['gourmet_sizes'][$size_key] ?? 15.00);
    if (isset($data['pizza_sizes'])) return (float) ($data['pizza_sizes'][$item][$size_key] ?? 0);
    $sizes = $data['sizes'] ?? [];
    if (isset($sizes[$item]) && is_array($sizes[$item])) return (float) ($sizes[$item][$size_key] ?? 0);
    return (float) ($sizes[$item] ?? 0);
}

function get_size_options(string $category, string $item): ?array {
    $menu = get_menu();
    $data = $menu['items'][$category] ?? null;
    if (!$data) return null;
    if (isset($data['price'])) return null;
    if (isset($data['gourmet_sizes'])) return array_keys($data['gourmet_sizes']);
    if (isset($data['pizza_sizes'])) return array_keys($data['pizza_sizes'][$item] ?? []);
    $sizes = $data['sizes'] ?? null;
    if (!$sizes) return null;
    if (isset($sizes[$item]) && is_array($sizes[$item])) return array_keys($sizes[$item]);
    return null;
}
