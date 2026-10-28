<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function page_start(string $title, string $description, string $bodyClass = '', string $path = '', bool $indexable = true): void
{
    $site = settings();
    $siteUrl = normalized_site_url($site);
    $canonical = $siteUrl !== null && $path !== '' ? $siteUrl . ($path === '/' ? '/' : '/' . ltrim($path, '/')) : null;
    ?>
<!doctype html>
<html lang="uk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0a0908">
  <title><?= h($title) ?></title>
  <meta name="description" content="<?= h($description) ?>">
  <?php if (!$indexable): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
  <?php if ($canonical): ?><link rel="canonical" href="<?= h($canonical) ?>"><?php endif; ?>
  <meta property="og:title" content="<?= h($title) ?>">
  <meta property="og:description" content="<?= h($description) ?>">
  <meta property="og:type" content="website">
  <?php if ($canonical): ?><meta property="og:url" content="<?= h($canonical) ?>"><?php endif; ?>
  <meta property="og:locale" content="uk_UA">
  <?php if ($siteUrl !== null): ?><meta property="og:image" content="<?=h($siteUrl)?>/assets/img/%D0%A1%D0%B5%D1%82%20_Elite%20Relax_.webp"><?php endif; ?>
  <link rel="icon" href="/assets/img/sushi-relax-logo.webp" type="image/webp">
  <link rel="apple-touch-icon" href="/assets/img/sushi-relax-logo.webp">
    <link rel="stylesheet" href="/assets/css/styles.css?v=20261028-39">
</head>
<body class="<?= h($bodyClass) ?>">
<a class="skip-link" href="#content">До вмісту</a>
<header class="site-header">
  <a class="brand" href="/" aria-label="Sushi Relax - на головну">
    <img class="brand-logo" src="/assets/img/sushi-relax-logo.webp" alt="" width="50" height="50">
    <span>Sushi <em>Relax</em></span>
  </a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Відкрити меню"><span class="nav-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span><span class="sr-only">Меню</span></button>
  <div class="nav-backdrop" data-nav-close hidden></div>
  <nav id="site-nav" class="site-nav" aria-label="Основна навігація">
    <button class="nav-close" type="button" data-nav-close aria-label="Закрити меню">×</button>
    <a href="/#menu">Меню</a><a href="/delivery.html">Доставка та оплата</a><a href="/about.html">Про нас</a><a href="/contacts.html">Контакти</a><a href="/qr-menu/">QR-меню</a>
  </nav>
  <?php if (!in_array($bodyClass, ['qr-mode','success-mode','reservation-mode'], true)): ?>
    <button class="cart-button" type="button" data-cart-open aria-controls="cart-sheet" aria-label="Відкрити кошик">
      <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 7H6"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg>
      <span class="cart-button-label">Кошик</span><span data-cart-count aria-live="polite" hidden>0</span>
    </button>
  <?php endif; ?>
</header>
<?php
}

function page_end(bool $withCart = true, array $scripts = []): void
{
    $site = settings();
    $instagram = normalized_instagram_url($site['instagram_url'] ?? null);
    $phone = normalized_phone($site['phone'] ?? null);
    $phoneHref = phone_href($phone);
    if ($withCart) cart_sheet();
    ?>
<footer class="site-footer">
  <div class="site-footer-main">
    <a class="brand brand--footer" href="/"><img class="brand-logo" src="/assets/img/sushi-relax-logo.webp" alt="" width="64" height="64"><span>Sushi <em>Relax</em></span></a>
    <address class="footer-contacts">
      <?php if($phone && $phoneHref):?><a class="footer-contact phone-link" href="<?=h($phoneHref)?>"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9 8.1 6.9 9.8a15.3 15.3 0 0 0 7.3 7.3l1.7-2.1 4.6 2.4-.8 3.1c-.2.8-1 1.4-1.8 1.4C9.2 21.9 2.1 14.8 2.1 6.1c0-.8.6-1.6 1.4-1.8l3.1-.8Z"/></svg><span><?=h($phone)?></span></a><?php else:?><p class="footer-contact"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9 8.1 6.9 9.8a15.3 15.3 0 0 0 7.3 7.3l1.7-2.1 4.6 2.4-.8 3.1c-.2.8-1 1.4-1.8 1.4C9.2 21.9 2.1 14.8 2.1 6.1c0-.8.6-1.6 1.4-1.8l3.1-.8Z"/></svg><span>Телефон буде додано</span></p><?php endif;?>
      <?php if($instagram):?><a class="footer-contact" href="<?=h($instagram)?>" target="_blank" rel="noopener noreferrer"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg><span>@<?=h(basename(rtrim($instagram,'/')))?></span></a><?php endif;?>
      <p class="footer-contact"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span><?=h(location_text($site))?></span></p>
    </address>
  </div>
  <div class="site-footer-bottom"><p>© <span data-year></span> Sushi Relax. Усі права захищені.</p><a href="/privacy.html">Політика конфіденційності</a></div>
  <p class="site-footer-credit"><span>Розробка сайту</span><a href="https://t.me/full_stack_lab" target="_blank" rel="noopener noreferrer">@full_stack_lab</a></p>
</footer>
<?php if ($withCart): ?>
<button class="cart-fab" type="button" data-cart-open aria-controls="cart-sheet" aria-label="Відкрити кошик">
  <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 9.2a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L20 7H6"/><circle cx="9" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/></svg>
  <span data-cart-count aria-live="polite" hidden>0</span>
</button>
<?php endif; ?>
<button class="to-top" type="button" aria-label="Повернутися вгору">↑</button>
<script src="/assets/js/app.js?v=20261028-12" defer></script>
<?php foreach ($scripts as $script): ?><script src="<?=h($script)?>" defer></script><?php endforeach; ?>
</body>
</html>
<?php
}

function phone_actions(array $site): void
{
    $phone = normalized_phone($site['phone'] ?? null); $href = phone_href($phone);
    if ($phone === null || $href === null) { echo '<p>Телефон буде додано</p>'; return; }
    ?>
<a class="phone-link" href="<?=h($href)?>"><?=h($phone)?></a>
<?php
}

function weekly_hours(array $site): void
{
    $days=['mon'=>'Понеділок','tue'=>'Вівторок','wed'=>'Середа','thu'=>'Четвер','fri'=>'П’ятниця','sat'=>'Субота','sun'=>'Неділя'];
    $configured=array_filter($site['hours']??[], 'is_array');
    if(!$configured){echo '<p>Робочі години ще не заповнені.</p>';return;}
    echo '<dl class="hours-list">';
    foreach($days as$key=>$label){$hours=$site['hours'][$key]??null;echo '<div><dt>'.h($label).'</dt><dd>'.(is_array($hours)?h(($hours['open']??'').'–'.($hours['close']??'')):'Вихідний').'</dd></div>';}
    echo '</dl>';
}

function order_hours_text(array $site): ?string
{
    $hours = $site['order_hours'] ?? null;
    if (!is_array($hours) || !preg_match('/^\d{2}:\d{2}$/', (string)($hours['open'] ?? '')) || !preg_match('/^\d{2}:\d{2}$/', (string)($hours['close'] ?? ''))) return null;
    return $hours['open'] . '–' . $hours['close'];
}

function location_text(array $site): string
{
    $parts=array_values(array_filter([trim((string)($site['city']??'')),trim((string)($site['address']??''))],static fn(string $value):bool=>$value!==''));
    return $parts?implode(', ',$parts):'Адреса буде додана';
}

function ingredient_list(string $ingredients): void
{
    $items = preg_split('/\s*[;,]\s*/u', trim($ingredients)) ?: [];
    $items = array_values(array_filter(array_map('trim', $items), static fn(string $item): bool => $item !== ''));
    if (!$items) return;

    echo '<span class="ingredient-list">';
    foreach ($items as $item) {
        $item = mb_strtoupper(mb_substr($item, 0, 1)) . mb_substr($item, 1);
        echo '<span>' . h($item) . '</span>';
    }
    echo '</span>';
}

function product_card(array $product, bool $orderable = true, array $allProducts = [], bool $withAnchor = true): void
{
    $currentPrice = active_product_price($product); $regularPrice = $product['price_minor'] ?? null;
    $hasPrice = is_int($currentPrice); $options = product_options($product); $payload = product_cart_payload($product, $allProducts);
    $optionMode = ($product['option_mode'] ?? 'multi') === 'single' ? 'single' : 'multi';
    $optionsRequired = ($product['options_required'] ?? false) === true;
    ?>
<article class="product-card"<?= $withAnchor ? ' id="product-' . h($product['id'] ?? '') . '"' : '' ?> data-product data-product-id="<?= h($product['id'] ?? '') ?>" data-search="<?= h(mb_strtolower(($product['name'] ?? '') . ' ' . ($product['ingredients'] ?? ''))) ?>">
  <figure class="product-media"><img src="<?= h(product_image($product)) ?>" alt="<?= h($product['name']) ?>" loading="lazy" width="600" height="450"></figure>
  <div class="product-content">
    <header class="product-heading"><h3><?= h($product['name']) ?></h3><?php if (!empty($product['badge'])): ?><span class="badge"><?= h($product['badge']) ?></span><?php endif; ?></header>
    <?php if ($product['description'] ?? null): ?><p><?= h($product['description']) ?></p><?php endif; ?>
    <dl class="product-meta">
      <?php if ($product['ingredients'] ?? null): ?><div class="ingredients-row"><dt>Склад</dt><dd><?php ingredient_list((string)$product['ingredients']); ?></dd></div><?php endif; ?>
      <?php if ($product['weight_g'] ?? null): ?><div><dt>Вага</dt><dd><?= (int) $product['weight_g'] ?> г</dd></div><?php endif; ?>
      <?php if ($product['prep_minutes'] ?? null): ?><div><dt>Час</dt><dd>≈ <?= (int) $product['prep_minutes'] ?> хв</dd></div><?php endif; ?>
    </dl>
    <?php if ($options): ?><fieldset class="product-options" data-product-options<?= $optionsRequired ? ' data-product-options-required' : '' ?>><legend><?=h((string)($product['option_label'] ?? 'Додатково'))?></legend><?php foreach($options as $option): ?>
      <?php if($orderable): ?><label><input type="<?=$optionMode==='single'?'radio':'checkbox'?>"<?= $optionMode === 'single' ? ' name="product-option-' . h((string)$product['id']) . '"' : '' ?> data-product-option value="<?=h($option['id'])?>" data-name="<?=h($option['name'])?>" data-price="<?=(int)$option['price_minor']?>"><span><?=h($option['name'])?><?= $option['price_minor'] > 0 ? ' · +' . h(money($option['price_minor'])) : '' ?></span></label><?php else: ?><span><?=h($option['name'])?><?= $option['price_minor'] > 0 ? ' · +' . h(money($option['price_minor'])) : '' ?></span><?php endif; ?>
    <?php endforeach;?><?php if($optionsRequired&&$orderable):?><small class="product-option-error" data-product-option-error hidden>Оберіть один соус.</small><?php endif;?></fieldset><?php endif; ?>
    <footer class="product-buy"><strong data-price-display><?php if(is_int($currentPrice) && is_int($regularPrice) && $currentPrice < $regularPrice): ?><del><?=h(money($regularPrice))?></del> <ins><?=h(money($currentPrice))?></ins><?php else: ?><?= h(money($currentPrice)) ?><?php endif; ?></strong>
      <?php if ($orderable): ?><div class="product-actions"><div class="qty product-qty" aria-label="Кількість <?=h($product['name'])?>"><button type="button" data-product-qty="minus" aria-label="Зменшити кількість">−</button><span data-product-quantity aria-live="polite">1</span><button type="button" data-product-qty="plus" aria-label="Збільшити кількість">+</button></div><button type="button" class="button button--small" data-add-product='<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>' <?= $hasPrice ? '' : 'disabled title="Додайте ціну в адмінпанелі"' ?>>Додати</button></div><?php endif; ?>
    </footer>
  </div>
</article>
<?php
}

function drink_menu_item(array $product, bool $orderable = true, array $allProducts = []): void
{
    $currentPrice = active_product_price($product); $regularPrice = $product['price_minor'] ?? null;
    $hasPrice = is_int($currentPrice); $payload = product_cart_payload($product, $allProducts);
    ?>
<article class="drink-menu-item" id="product-<?=h($product['id'] ?? '')?>" data-product data-product-id="<?=h($product['id'] ?? '')?>" data-search="<?=h(mb_strtolower(($product['name'] ?? '') . ' ' . ($product['ingredients'] ?? '')))?>">
  <header class="drink-menu-copy">
    <h3><?=h($product['name'])?></h3>
    <?php if (($product['ingredients'] ?? null) && ($product['category'] ?? '') !== 'coffee'): ?><p><?=h($product['ingredients'])?></p><?php endif; ?>
  </header>
  <footer class="drink-menu-buy">
    <strong data-price-display><?php if(is_int($currentPrice) && is_int($regularPrice) && $currentPrice < $regularPrice): ?><del><?=h(money($regularPrice))?></del> <ins><?=h(money($currentPrice))?></ins><?php else: ?><?=h(money($currentPrice))?><?php endif; ?></strong>
    <?php if ($orderable): ?><button type="button" class="button button--small" data-add-product='<?=h(json_encode($payload, JSON_UNESCAPED_UNICODE))?>' <?= $hasPrice ? '' : 'disabled title="Додайте ціну в адмінпанелі"' ?>>Додати</button><?php endif; ?>
  </footer>
</article>
<?php
}

function product_cart_payload(array $product, array $allProducts): array
{
    $currentPrice = active_product_price($product); $options = product_options($product); $recommendations = [];
    $recommendationIds = array_values(array_filter($product['recommendations'] ?? [], 'is_string'));
    foreach ($allProducts as $candidate) {
        $candidatePrice = active_product_price($candidate);
        if (in_array($candidate['id'] ?? '', $recommendationIds, true) && ($candidate['available'] ?? false) && is_int($candidatePrice)) {
            $recommendations[] = ['id'=>$candidate['id'],'name'=>$candidate['name'],'price_minor'=>$candidatePrice,'weight_g'=>$candidate['weight_g']??null];
        }
    }
    return ['id'=>$product['id'],'name'=>$product['name'],'price_minor'=>$currentPrice,'weight_g'=>$product['weight_g']??null,'delivery_available'=>($product['delivery_available']??true)!==false,'available_options'=>$options,'option_mode'=>(($product['option_mode']??'multi')==='single'?'single':'multi'),'options_required'=>($product['options_required']??false)===true,'option_label'=>(string)($product['option_label']??'Додатково'),'recommendations'=>$recommendations];
}

function category_navigation(array $catalog): void
{
    $links = [];
    foreach ($catalog['categories'] ?? [] as $category) {
        $categoryId = (string)($category['id'] ?? '');
        $hasProducts = array_filter($catalog['products'] ?? [], static fn(array $product): bool => ($product['category'] ?? '') === $categoryId);
        if ($categoryId === '' || !$hasProducts) continue;
        $anchor = 'category-' . preg_replace('/[^a-z0-9_-]+/i', '-', $categoryId);
        $links[] = ['anchor' => $anchor, 'name' => (string)($category['name'] ?? '')];
    }
    if (!$links) return;
    ?>
<nav class="qr-category-nav" aria-label="Категорії меню">
<button class="category-scroll-button category-scroll-button--left" type="button" data-category-scroll="left" aria-label="Прокрутити категорії вліво"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
<ul class="qr-category-nav-list">
  <?php foreach($links as $index => $link):?><li><a href="#<?=h($link['anchor'])?>" data-category-link<?= $index === 0 ? ' aria-current="true"' : '' ?>><?=h($link['name'])?></a></li><?php endforeach;?>
</ul>
<button class="category-scroll-button category-scroll-button--right" type="button" data-category-scroll="right" aria-label="Прокрутити категорії вправо"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
</nav>
<?php
}

function menu_catalog(array $catalog, bool $orderable = true): void
{
    $drinkCategories = ['cocktails', 'milkshakes', 'coffee', 'hot-drinks'];
    foreach ($catalog['categories'] as $index => $category) {
        $products = array_values(array_filter($catalog['products'], fn($p) => $p['category'] === $category['id']));
        if (!$products) continue;
        $categorySlug = preg_replace('/[^a-z0-9_-]+/i', '-', (string)($category['id'] ?? $index));
        $panelId = 'category-panel-' . $categorySlug;
        ?>
<section class="menu-group" id="category-<?=h($categorySlug)?>" aria-labelledby="category-title-<?=h($categorySlug)?>" data-menu-group>
  <button class="category-trigger" type="button" aria-expanded="true" aria-controls="<?=h($panelId)?>">
    <span id="category-title-<?=h($categorySlug)?>"><small><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></small><?= h($category['name']) ?></span><span aria-hidden="true">＋</span>
  </button>
  <div class="category-panel" id="<?=h($panelId)?>">
    <?php if (in_array($category['id'], $drinkCategories, true)): ?>
      <div class="drink-menu-list"><?php foreach ($products as $product) drink_menu_item($product, $orderable, $catalog['products']); ?></div>
    <?php else: ?>
      <div class="product-grid"><?php foreach ($products as $product) product_card($product, $orderable, $catalog['products']); ?></div>
    <?php endif; ?>
  </div>
</section>
<?php
    }
}

function cart_sheet(): void
{
    ?>
<div class="sheet-backdrop" data-cart-close hidden></div>
<aside class="cart-sheet" id="cart-sheet" role="dialog" aria-modal="true" aria-labelledby="cart-title" aria-hidden="true" tabindex="-1">
  <button class="sheet-handle" type="button" data-cart-drag aria-label="Потягніть вниз, щоб закрити кошик"><span aria-hidden="true"></span></button>
  <header><hgroup><p class="eyebrow">Ваше замовлення</p><h2 id="cart-title">Кошик</h2></hgroup><button class="icon-button" type="button" data-cart-close aria-label="Закрити кошик"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button></header>
  <section class="cart-items" aria-label="Позиції кошика" data-cart-items><p class="empty-state">Кошик поки порожній.</p></section>
  <dl class="cart-summary"><div><dt>Позицій</dt><dd data-cart-lines>0</dd></div><div><dt>Одиниць</dt><dd data-cart-quantity>0</dd></div><div><dt>Орієнтовна вага</dt><dd data-cart-weight>-</dd></div><div class="cart-total"><dt>Попередня сума</dt><dd data-cart-total>0 ₴</dd></div></dl>
  <a class="button button--wide" href="/checkout.html" data-checkout-link aria-disabled="true">Оформити замовлення</a>
</aside>
<?php
}
