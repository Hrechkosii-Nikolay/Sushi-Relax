<?php
declare(strict_types=1);

/**
 * One-time catalog import from the photographed Sushi Relax menu supplied in
 * October 2026. Existing product IDs and images are preserved.
 */

$catalogPath = __DIR__ . '/../data/catalog.json';
$catalog = json_decode((string) file_get_contents($catalogPath), true, 512, JSON_THROW_ON_ERROR);

$catalog['categories'] = [
    ['id' => 'sets', 'name' => 'Сети', 'order' => 10],
    ['id' => 'philadelphia', 'name' => 'Філадельфія', 'order' => 20],
    ['id' => 'california', 'name' => 'Каліфорнія', 'order' => 30],
    ['id' => 'dragons', 'name' => 'Дракони', 'order' => 40],
    ['id' => 'baked', 'name' => 'Запечені роли', 'order' => 50],
    ['id' => 'rolls', 'name' => 'Роли та макі', 'order' => 60],
    ['id' => 'hot-dishes', 'name' => 'Гарячі страви', 'order' => 64],
    ['id' => 'udon', 'name' => 'Удон', 'order' => 65],
    ['id' => 'funchose', 'name' => 'Фунчоза', 'order' => 66],
    ['id' => 'syrnyky', 'name' => 'Сирники', 'order' => 67],
    ['id' => 'sweet-rolls', 'name' => 'Солодкі роли', 'order' => 70],
    ['id' => 'sweet-sets', 'name' => 'Солодкі сети', 'order' => 80],
    ['id' => 'cocktails', 'name' => 'Коктейлі', 'order' => 90],
    ['id' => 'milkshakes', 'name' => 'Молочні коктейлі', 'order' => 100],
    ['id' => 'coffee', 'name' => 'Кава', 'order' => 110],
    ['id' => 'hot-drinks', 'name' => 'Гарячі напої', 'order' => 120],
];

$updates = [
    'philadelphia-mango-eel' => [31000, 250, 'Вершковий сир, креветка, манго, вугор, білий кунжут, соус унагі'],
    'philadelphia-cheese' => [27000, 250, 'Вершковий сир, сир чедер, лосось, салат айсберг, ікра масаго, соус шрірача, стружка тунця'],
    'philadelphia-salmon' => [25000, 235, 'Вершковий сир, лосось, авокадо'],
    'philadelphia-lux' => [31000, 280, 'Вершковий сир, вугор, огірок, салат айсберг, лосось'],
    'philadelphia-shrimp' => [25000, 225, 'Вершковий сир, креветка, авокадо, ікра масаго'],
    'philadelphia-eel' => [26000, 240, 'Вершковий сир, вугор, огірок, білий кунжут, соус унагі'],
    'philadelphia-smoked-salmon' => [18000, 230, 'Вершковий сир, копчений лосось, огірок, білий кунжут'],
    'philadelphia-green-avocado' => [19000, 240, 'Вершковий сир, лосось, авокадо, огірок, чорний кунжут, соус світ чилі'],
    'philadelphia-green-mango' => [20000, 240, 'Вершковий сир, лосось, манго, огірок, чорний кунжут, соус світ чилі'],
    'philadelphia-cheesy' => [25000, 255, 'Подвійний вершковий сир, лосось'],
    'california-tuna' => [19000, 240, 'Вершковий сир, тунець, огірок, ікра масаго'],
    'california-eel' => [22000, 255, 'Вершковий сир, вугор, огірок, білий і чорний кунжут, соус унагі'],
    'red-dragon' => [28000, 255, 'Вершковий сир, креветка, огірок, лосось'],
    'gold-dragon' => [31000, 260, 'Вершковий сир, креветка, огірок, вугор, білий кунжут, соус унагі'],
    'alaska' => [19000, 235, 'Вершковий сир, лосось, авокадо, ікра масаго'],
    'felix-chicken' => [16000, 240, 'Вершковий сир, курка, огірок, білий і чорний кунжут, соус унагі'],
    'felix-tuna' => [20000, 240, 'Вершковий сир, тунець, ікра масаго, огірок, чорний кунжут, соус унагі'],
    'felix-fried-salmon' => [22000, 260, 'Вершковий сир, смажений лосось, салат айсберг, огірок, білий і чорний кунжут, соус світ чилі'],
    'tuna-roll' => [21000, 240, 'Вершковий сир, тунець, огірок, стружка тунця, соус унагі'],
    'futomaki-smoked-salmon' => [19000, 240, 'Вершковий сир, копчений лосось, салат айсберг, огірок, ікра масаго'],
    'futosurimi' => [17000, 220, 'Вершковий сир, крабова паличка, ікра масаго, білий кунжут, соус унагі'],
    'baked-shrimp' => [28000, 305, 'Сир, креветка, пармезан, огірок'],
    'baked-chicken' => [28000, 284, 'Сир, курка, ананас, моцарела, кунжут'],
    'baked-smoked-salmon' => [28000, 284, 'Сир, копчений лосось, пармезан, ікра масаго, огірок'],
    'set-elite-relax' => [145000, 1500, 'Філадельфія люкс; Філадельфія з манго у вугрі; Фелікс рол з тунцем; Фелікс рол зі смаженим лососем; Філадельфія Cheese; Червоний дракон'],
    'set-vip-relax' => [85000, 750, 'Філадельфія люкс; Філадельфія з манго у вугрі; Фелікс рол з тунцем'],
    'set-chef' => [125000, 1650, 'Філадельфія класик; Каліфорнія з вугром; Рол з тунцем; Фелікс рол з куркою; Футо сурімі; Фелікс рол зі смаженим лососем; Каліфорнія з тунцем'],
    'set-weekend' => [100000, 1200, 'Філадельфія класик; Каліфорнія з вугром; Рол з тунцем; Фелікс рол зі смаженим лососем; Каліфорнія з тунцем'],
    'set-family' => [90000, 1150, 'Філадельфія класик; Каліфорнія з вугром; Рол з тунцем; Фелікс рол з куркою; Футо сурімі'],
    'set-philadelphia' => [70000, 750, 'Філадельфія класик; Каліфорнія з вугром; Рол з тунцем'],
    'set-fire' => [85000, 895, 'Рол запечений з копченим лососем; рол запечений з креветкою; рол запечений з куркою'],
];

$nameUpdates = [
    'futosurimi' => 'Футо сурімі',
    'set-chef' => 'Сет «Від шефа»',
];

foreach ($catalog['products'] as &$product) {
    if (!isset($updates[$product['id']])) {
        continue;
    }
    [$product['price_minor'], $product['weight_g'], $product['ingredients']] = $updates[$product['id']];
    $product['description'] = null;
    if (isset($nameUpdates[$product['id']])) {
        $product['name'] = $nameUpdates[$product['id']];
    }
}
unset($product);

function importedProduct(string $id, string $category, string $name, string $image, int $price, ?int $weight, string $ingredients, int $order, array $options = [], string $optionMode = 'multi', bool $optionsRequired = false, string $optionLabel = 'Додатково'): array
{
    $product = [
        'id' => $id,
        'category' => $category,
        'name' => $name,
        'image' => $image,
        'price_minor' => $price,
        'weight_g' => $weight,
        'prep_minutes' => null,
        'description' => null,
        'ingredients' => $ingredients,
        'available' => true,
        'order' => $order,
    ];
    if ($options) {
        $product['options'] = $options;
        $product['option_mode'] = $optionMode;
        $product['options_required'] = $optionsRequired;
        $product['option_label'] = $optionLabel;
    }
    return $product;
}

$newProducts = [
    importedProduct('philadelphia-classic', 'philadelphia', 'Філадельфія класик', 'philadelphia-classic.png', 25000, 235, 'Вершковий сир, лосось, огірок', 15),
    importedProduct('maki-cucumber', 'rolls', 'Макі з огірком', 'maki-cucumber.png', 8000, 110, 'Норі, рис, огірок', 70),
    importedProduct('maki-salmon', 'rolls', 'Макі з лососем', 'maki-salmon.png', 9000, 120, 'Норі, рис, лосось', 80),
    importedProduct('baked-eel-shrimp', 'baked', 'Запечений рол з вугром та креветкою', 'baked-eel-shrimp.png', 28000, 320, 'Рис, крем-сир, вугор, креветка, огірок, мікс сирів', 40),
    importedProduct('baked-tuna', 'baked', 'Запечений рол з тунцем', 'baked-tuna.png', 28000, 318, 'Рис, крем-сир, тунець, огірок, мікс сирів, фірмовий соус, стружка тунця', 50),

    importedProduct('pork-steak', 'hot-dishes', 'Стейк', 'steak.webp', 30000, 350, 'Свинячий балик, овочі гриль', 10, [
        ['id' => 'sweet-chili', 'name' => 'Соус світ чилі, 50 г', 'price_minor' => 1000],
        ['id' => 'mild-ketchup', 'name' => 'Кетчуп лагідний, 50 г', 'price_minor' => 1000],
        ['id' => 'bbq', 'name' => 'Соус BBQ, 50 г', 'price_minor' => 1000],
    ], 'single', false, 'Соус на вибір (необов’язково)'),

    importedProduct('udon-chicken', 'udon', 'Удон з куркою', 'udon-chicken.png', 19000, 250, 'Локшина удон, мікс овочів, куряче філе, азійські соуси, кунжут', 10),
    importedProduct('udon-shrimp', 'udon', 'Удон з креветками', 'udon-shrimp.png', 21500, 250, 'Локшина удон, креветки, мікс овочів, азійські соуси, кунжут', 20),

    importedProduct('funchose-shrimp', 'funchose', 'Фунчоза з креветками', 'funchose-shrimp.png', 20000, 250, 'Скляна локшина, креветки, овочі, соус світ чилі, кунжут', 10),
    importedProduct('funchose-chicken', 'funchose', 'Фунчоза з куркою', 'funchose-chicken.png', 17000, 250, 'Скляна локшина, куряче філе, овочі, соус світ чилі, кунжут', 20),
    importedProduct('funchose-pork', 'funchose', 'Фунчоза зі свининою', 'funchose-pork.png', 18000, 250, 'Скляна локшина, свинячий балик, мікс овочів, соус світ чилі, кунжут', 30),

    importedProduct('syrnyky-classic', 'syrnyky', 'Класичні сирники', 'syrnyky-classic.webp', 9000, 200, '', 10, [
        ['id' => 'sour-cream', 'name' => 'Сметана', 'price_minor' => 0],
        ['id' => 'condensed-milk', 'name' => 'Згущене молоко', 'price_minor' => 0],
        ['id' => 'jam', 'name' => 'Джем', 'price_minor' => 0],
    ], 'single', true, 'Соус на вибір'),
    importedProduct('syrnyky-raisins', 'syrnyky', 'Сирники з родзинками', 'syrnyky-raisins.webp', 9500, 200, '', 20, [
        ['id' => 'sour-cream', 'name' => 'Сметана', 'price_minor' => 0],
        ['id' => 'condensed-milk', 'name' => 'Згущене молоко', 'price_minor' => 0],
        ['id' => 'jam', 'name' => 'Джем', 'price_minor' => 0],
    ], 'single', true, 'Соус на вибір'),

    importedProduct('sweet-forest-berries', 'sweet-rolls', 'Солодкий рол «Лісові ягоди»', 'sweet-forest-berries.png', 17000, 240, 'Ананас, рисове тісто, вершковий сир, ягідний топінг, цукрова пудра', 10),
    importedProduct('sweet-caramel', 'sweet-rolls', 'Солодкий рол з карамеллю', 'sweet-caramel.png', 17000, 240, 'Банан, рисове тісто, вершковий сир, карамельний топінг, цукрова пудра', 20),
    importedProduct('sweet-oreo-nutella', 'sweet-rolls', 'Солодкий рол «Орео-Нутелла»', 'sweet-oreo-nutella.png', 22000, 295, 'Банан, рисове тісто, вершковий сир, печиво Oreo, шоколадний топінг', 30),
    importedProduct('sweet-cherry', 'sweet-rolls', 'Солодкий рол з вишнею', 'sweet-cherry.png', 17000, 240, 'Банан, рисове тісто, вершковий сир, вишневий топінг, цукрова пудра', 40),
    importedProduct('sweet-strawberry', 'sweet-rolls', 'Солодкий рол з полуницею', 'sweet-strawberry.png', 17000, 240, 'Рисове тісто, вершковий сир, полуничний топінг, цукрова пудра, банан', 50),
    importedProduct('sweet-raspberry', 'sweet-rolls', 'Солодкий рол з малиною', 'sweet-raspberry.png', 17000, 240, 'Банан, рисове тісто, вершковий сир, малиновий топінг, цукрова пудра', 60),
    importedProduct('sweet-set-big-love', 'sweet-sets', 'Солодкий сет «Моє велике кохання»', 'sweet-set-large.png', 73000, 1020, 'Солодкий рол з полуницею; «Лісові ягоди»; з карамеллю; «Орео-Нутелла»', 10),
    importedProduct('sweet-set-small-love', 'sweet-sets', 'Солодкий сет «Моє маленьке кохання»', 'sweet-set-small.png', 56000, 965, 'Солодкий рол з полуницею; «Лісові ягоди»; з карамеллю', 20),

    importedProduct('cocktail-sex-on-the-beach', 'cocktails', 'Б/а Секс на пляжі', 'drinks-cocktails.png', 7000, null, 'Апельсиновий сік, гранатовий сік, сироп, лимонний сік, лід', 10),
    importedProduct('cocktail-pina-colada', 'cocktails', 'Б/а Піна колада', 'drinks-cocktails.png', 7000, null, 'Ананасовий сік, вершки, сироп, лід', 20),
    importedProduct('cocktail-mojito', 'cocktails', 'Б/а Мохіто', 'drinks-cocktails.png', 7000, null, 'Спрайт, лимонний сік, лайм, м’ята, сироп, лід', 30),
    importedProduct('cocktail-blue-lagoon', 'cocktails', 'Б/а Блакитна лагуна', 'drinks-cocktails.png', 7000, null, 'Спрайт, сироп, лимонний сік, лимон, лід', 40),
    importedProduct('cocktail-barbie', 'cocktails', 'Б/а Барбі', 'drinks-cocktails.png', 7000, null, 'Ананасовий сік, сік банан-полуниця, сироп, згущене молоко, вершки, лід', 50),
    importedProduct('cocktail-blue-orange', 'cocktails', 'Б/а Blue Orange', 'drinks-cocktails.png', 7000, null, 'Спрайт, апельсиновий сік, сироп, лід', 60),
    importedProduct('cocktail-sicily', 'cocktails', 'Б/а Сицилія', 'drinks-cocktails.png', 7000, null, 'Schweppes, апельсиновий сік, сироп, лід', 70),

    importedProduct('milkshake-cherry', 'milkshakes', 'Молочний коктейль «Вишня»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, вишневий топінг', 10),
    importedProduct('milkshake-strawberry', 'milkshakes', 'Молочний коктейль «Полуниця»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, полуничний топінг', 20),
    importedProduct('milkshake-chocolate', 'milkshakes', 'Молочний коктейль «Шоколад»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, шоколадний топінг', 30),
    importedProduct('milkshake-caramel', 'milkshakes', 'Молочний коктейль «Карамель»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, карамельний топінг', 40),
    importedProduct('milkshake-orange', 'milkshakes', 'Молочний коктейль «Апельсин»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, апельсиновий топінг', 50),
    importedProduct('milkshake-bubble-gum', 'milkshakes', 'Молочний коктейль «Bubble Gum»', 'drinks-milkshakes.png', 7500, null, 'Морозиво, молоко, вершки, топінг Blue Curacao', 60),

    importedProduct('coffee-espresso', 'coffee', 'Еспресо', 'drinks-coffee.png', 2500, null, 'Кава еспресо', 10),
    importedProduct('coffee-americano', 'coffee', 'Американо', 'drinks-coffee.png', 3000, null, 'Кава американо', 20),
    importedProduct('coffee-americano-milk', 'coffee', 'Американо з молоком', 'drinks-coffee.png', 4500, null, 'Кава американо, молоко', 30),
    importedProduct('coffee-cappuccino', 'coffee', 'Капучино', 'drinks-coffee.png', 4500, null, 'Еспресо, молоко', 40),
    importedProduct('coffee-latte', 'coffee', 'Лате', 'drinks-coffee.png', 5000, null, 'Еспресо, молоко', 50),
    importedProduct('coffee-flat-white', 'coffee', 'Флет вайт', 'drinks-coffee.png', 4500, null, 'Еспресо, молоко', 60),
    importedProduct('coffee-matcha-anchan', 'coffee', 'Матча анчан', 'drinks-coffee.png', 5000, null, 'Матча анчан', 70),
    importedProduct('coffee-kids-cocoa', 'coffee', 'Дитяче какао', 'drinks-coffee.png', 5000, null, 'Какао, молоко', 80),
    importedProduct('coffee-natural-cocoa', 'coffee', 'Какао натуральне', 'drinks-coffee.png', 5000, null, 'Натуральне какао, молоко', 90),
    importedProduct('tea', 'coffee', 'Чай', 'drinks-coffee.png', 3000, null, 'Чай', 100),
    importedProduct('coffee-orange', 'coffee', 'Оранж', 'drinks-coffee.png', 6500, null, 'Кавовий напій з апельсином', 110),
    importedProduct('signature-tea', 'coffee', 'Фірмовий чай', 'drinks-coffee.png', 7500, null, 'Фірмовий чай', 120),

    importedProduct('hot-spiced-latte', 'hot-drinks', 'Пряне лате', 'drinks-hot.png', 7000, null, 'Допіо, молоко, кориця, спеції', 10),
    importedProduct('hot-toffee-cappuccino', 'hot-drinks', 'Капучино тоффі', 'drinks-hot.png', 6500, null, 'Еспресо, молоко, згущене молоко', 20),
    importedProduct('hot-apple-pie', 'hot-drinks', 'Apple Pie', 'drinks-hot.png', 6000, null, 'Яблучний сік, еспресо, ванільний цукор, сироп', 30),
    importedProduct('hot-cocoa-marshmallow', 'hot-drinks', 'Какао з маршмелоу', 'drinks-hot.png', 7000, null, 'Какао, молоко, вершки, маршмелоу', 40),
    importedProduct('hot-apple-punch', 'hot-drinks', 'Яблучний пунш', 'drinks-hot.png', 6500, null, 'Яблучний сік, мед, апельсин, кориця, імбир', 50),
    importedProduct('hot-mulled-zero', 'hot-drinks', 'Глінтвейн б/а', 'drinks-hot.png', 6500, null, 'Вишневий сік, апельсиновий сік, кориця, гвоздика, мед, апельсин', 60),
    importedProduct('hot-signature-tea', 'hot-drinks', 'Авторський чай', 'drinks-hot.png', 7500, null, 'Обліпиха, імбир, лісові ягоди, пюре, чай, фрукти', 70),
    importedProduct('hot-nut-honey-raf', 'hot-drinks', 'Горіхово-медовий раф', 'drinks-hot.png', 6500, null, 'Еспресо, молоко, вершки, арахісова паста, мед', 80),
    importedProduct('hot-nut-cappuccino', 'hot-drinks', 'Горіховий капучино', 'drinks-hot.png', 6500, null, 'Еспресо, молоко, арахісова паста, сироп', 90),
    importedProduct('hot-warm-relax', 'hot-drinks', 'Теплий Relax', 'drinks-hot.png', 7000, null, 'Ананасовий сік, персиковий сік, сироп, лимонний сік, збиті вершки', 100),
];

$existingIds = array_fill_keys(array_column($catalog['products'], 'id'), true);
foreach ($newProducts as $product) {
    if (!isset($existingIds[$product['id']])) {
        $catalog['products'][] = $product;
    }
}

foreach ($catalog['products'] as &$product) {
    $product['delivery_available'] = true;
}
unset($product);

file_put_contents(
    $catalogPath,
    json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL,
    LOCK_EX
);

echo 'Catalog updated: ' . count($catalog['products']) . " products\n";
