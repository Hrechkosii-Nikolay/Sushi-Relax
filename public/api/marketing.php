<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') send_json(['error'=>'Метод не підтримується.'], 405);

$catalogData = catalog();
$site = settings();
$products = [];
$promoIds = [];
foreach ($catalogData['products'] as $product) {
    $current = active_product_price($product);
    $regular = $product['price_minor'] ?? null;
    $id = (string)($product['id'] ?? '');
    if ($id === '') continue;
    $products[] = ['id'=>$id, 'price_minor'=>$current, 'regular_price_minor'=>$regular, 'delivery_available'=>($product['delivery_available']??true)!==false];
    if (is_int($current) && is_int($regular) && $current < $regular) $promoIds[] = $id;
}

$banners = array_map(static fn(array $banner): array => [
    'title'=>(string)($banner['title'] ?? ''),
    'link'=>(string)($banner['link'] ?? '#menu'),
    'image'=>product_image(['image'=>$banner['image'] ?? '']),
    'period_label'=>promotion_period_label($banner),
], active_banners($site, $catalogData));

$open = is_open_now($site);
send_json([
    'products'=>$products,
    'promo_product_ids'=>array_slice($promoIds, 0, 6),
    'banners'=>$banners,
    'orders_status'=>['open'=>$open, 'label'=>$open === true ? 'Відчинено' : ($open === false ? 'Зачинено' : 'Графік уточнюється')],
]);
