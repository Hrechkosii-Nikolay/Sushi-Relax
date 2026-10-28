<?php
require_once __DIR__ . '/view.php';
$catalog = catalog();
$site = settings();
$open = is_open_now($site);
$banners = active_banners($site, $catalog);
$promoProducts = array_values(array_filter($catalog['products'], static fn(array $product): bool => is_int($product['price_minor'] ?? null) && active_product_price($product) !== $product['price_minor']));
page_start('Sushi Relax - суші для вашого вечора', 'Меню Sushi Relax: роли та сети. Доставка, самовивіз або замовлення до столу на вибраний час.', '', '/');
$siteUrl = normalized_site_url($site);
if ($siteUrl !== null) {
    $schema = ['@context'=>'https://schema.org','@type'=>'Restaurant','name'=>$site['site_name']??'Sushi Relax','url'=>$siteUrl.'/','menu'=>$siteUrl.'/#menu','servesCuisine'=>'Суші','image'=>$siteUrl.'/assets/img/%D0%A1%D0%B5%D1%82%20_Elite%20Relax_.webp'];
    if (!empty($site['phone'])) $schema['telephone']=$site['phone'];
    if (!empty($site['address'])) $schema['address']=['@type'=>'PostalAddress','streetAddress'=>$site['address'],'addressLocality'=>$site['city']??''];
    if ($instagram = normalized_instagram_url($site['instagram_url'] ?? null)) $schema['sameAs']=[$instagram];
    echo '<script type="application/ld+json">'.json_encode($schema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
?>
<main id="content">
  <section class="hero" aria-labelledby="hero-title">
    <header class="hero-copy">
      <p class="eyebrow">Суші · роли · сети</p>
      <h1 id="hero-title">Смак, що<br><em>зупиняє час.</em></h1>
      <p class="hero-lead">Улюблені роли, яскраві смаки та атмосфера, до якої хочеться повертатися.</p>
      <div class="hero-actions"><a class="button" href="#menu">Замовити</a><a class="button button--reservation" href="/reservation.html"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg><span>Забронювати стіл</span></a></div>
      <p class="service-line" data-order-status><span class="status-dot <?= $open === true ? 'is-open' : '' ?>"></span><strong><?= $open === true ? 'Відчинено' : ($open === false ? 'Зачинено' : 'Графік уточнюється') ?></strong><span><?= h(location_text($site)) ?></span></p>
    </header>
    <figure class="hero-visual"><img src="/assets/img/hero-interior-3182.webp" alt="Затишний столик в інтер’єрі Sushi Relax" width="1200" height="1600" fetchpriority="high" decoding="async"></figure>
  </section>

  <section class="ticker" aria-label="Переваги"><span>ЇЖ · ПИЙ · НАСОЛОДЖУЙСЯ</span><i>✦</i><span>Доставка · самовивіз · у закладі</span><i>✦</i><span>Свіже приготування</span></section>

  <section class="promotions" aria-labelledby="promotions-title" data-banner-section<?= $banners ? '' : ' hidden' ?>><header class="section-heading"><hgroup><p class="eyebrow">Спеціальні пропозиції</p><h2 id="promotions-title">Акції</h2></hgroup></header><div class="banner-grid" data-banner-grid><?php foreach($banners as $banner):?><a class="promo-banner" href="<?=h($banner['link']??'#menu')?>"><img src="<?=h(product_image(['image'=>$banner['image']]))?>" alt="" loading="lazy"><span><small><?=h(promotion_period_label($banner))?></small><strong><?=h($banner['title'])?></strong></span></a><?php endforeach;?></div></section>

  <section class="promotions promo-products" aria-labelledby="promo-products-title" data-promo-products-section<?= $promoProducts ? '' : ' hidden' ?>><header class="section-heading"><hgroup><p class="eyebrow">Зараз вигідніше</p><h2 id="promo-products-title">Акційне меню</h2></hgroup></header><div class="product-grid" data-promo-products-grid><?php foreach(array_slice($promoProducts,0,6) as $product)product_card($product,true,$catalog['products'],false);?></div></section>

  <section class="menu-section" id="menu" aria-labelledby="menu-title">
    <header class="section-heading"><hgroup><p class="eyebrow">Оберіть своє</p><h2 id="menu-title">Меню</h2></hgroup></header>
    <?php category_navigation($catalog); ?>
    <search class="menu-search"><label class="search"><span class="sr-only">Пошук страв</span><input type="search" placeholder="Знайти рол або сет…" data-menu-search></label></search>
    <?php if (trim((string)($catalog['notice'] ?? '')) !== ''): ?><p class="catalog-notice"><?=h($catalog['notice'])?></p><?php endif; ?>
    <div data-menu-catalog><?php menu_catalog($catalog, true); ?></div>
    <p class="no-results" data-no-results hidden>Нічого не знайшли. Спробуйте іншу назву.</p>
  </section>

  <section class="steps" aria-labelledby="steps-title">
    <header class="section-heading"><hgroup><p class="eyebrow steps-eyebrow">Усе просто</p><h2 id="steps-title">Як замовити</h2></hgroup></header>
    <ol><li><span>01</span><h3>Оберіть</h3><p>Додайте страви з меню до кошика.</p></li><li><span>02</span><h3>Замовте</h3><p>Оберіть доставку або самовивіз і зручний час.</p></li><li><span>03</span><h3>Смакуйте</h3><p>Отримайте замовлення та насолоджуйтеся улюбленими стравами.</p></li></ol>
  </section>

  <section class="home-info" aria-labelledby="home-info-title">
    <header class="section-heading"><hgroup><p class="eyebrow">Коротко про головне</p><h2 id="home-info-title">Sushi Relax</h2></hgroup></header>
    <div class="info-grid">
      <article class="info-card"><h3>Про заклад</h3><p><?=h($site['about'])?></p><a class="text-link" href="/about.html">Дізнатися більше →</a></article>
      <article class="info-card"><h3>Доставка та самовивіз</h3><p>Мінімальна сума замовлення для доставки - <?=h(money($site['delivery']['minimum_minor']))?>. У межах міста доставка безкоштовна, за межі міста - на таксі за тарифом перевізника.</p><p>Доставка доступна з 11:00 і здійснюється за передплатою. Для самовивозу діє передплата 50%.</p><a class="text-link" href="/delivery.html">Умови отримання →</a></article>
      <article class="info-card"><h3>Контакти</h3><?php $homePhone=normalized_phone($site['phone']??null);$homePhoneHref=phone_href($homePhone);$homeTelegram=normalized_telegram_url($site['telegram_contact']??null);$homeInstagram=normalized_instagram_url($site['instagram_url']??null);?><div class="home-contact-list"><?php if($homePhone&&$homePhoneHref):?><a class="home-contact-row phone-link" href="<?=h($homePhoneHref)?>"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9 8.1 6.9 9.8a15.3 15.3 0 0 0 7.3 7.3l1.7-2.1 4.6 2.4-.8 3.1c-.2.8-1 1.4-1.8 1.4C9.2 21.9 2.1 14.8 2.1 6.1c0-.8.6-1.6 1.4-1.8l3.1-.8Z"/></svg><span><?=h($homePhone)?></span></a><?php endif;?><?php if($homeTelegram):?><a class="home-contact-row" href="<?=h($homeTelegram)?>" target="_blank" rel="noopener noreferrer"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.8 18-3.4-7-6.8-3.1L21 3Z"/><path d="m9.8 14 4.2-4"/></svg><span>Telegram</span></a><?php endif;?><?php if($homeInstagram):?><a class="home-contact-row" href="<?=h($homeInstagram)?>" target="_blank" rel="noopener noreferrer"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg><span>@<?=h(basename(rtrim($homeInstagram,'/')))?></span></a><?php endif;?><p class="home-contact-row"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span><?=h(location_text($site))?></span></p><?php if($orderHours=order_hours_text($site)):?><p class="home-contact-row"><svg class="footer-contact-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><span>Замовлення: <?=h($orderHours)?></span></p><?php endif;?></div><a class="text-link" href="/contacts.html">Графік і контакти →</a></article>
    </div>
  </section>

  <section class="split-info" aria-labelledby="split-info-title"><header><p class="eyebrow">Sushi Relax</p><h2 id="split-info-title">Ваш вечір.<br>Наші роли.</h2></header><div><p class="split-info-slogan">Смак, який збирає разом. Обирайте улюблені роли та створюйте вечір, до якого хочеться повертатися.</p><div class="split-info-actions"><a class="button" href="#menu">Замовити</a></div></div></section>

  <section class="seo-content" aria-labelledby="seo-content-title">
    <p class="eyebrow">Суші у вашому місті</p>
    <h2 id="seo-content-title">Суші та роли в Гадячі</h2>
    <div class="seo-content-grid">
      <section aria-labelledby="seo-menu-title">
        <h3 id="seo-menu-title">Меню Sushi Relax</h3>
        <p>Sushi Relax готує роли та сети в Гадячі для затишної вечері, зустрічі з друзями або святкового столу. У нашому меню представлені сети, класична «Філадельфія», «Каліфорнія», роли «Дракон», запечені та інші популярні позиції.</p>
        <p>Каталог містить фотографії та актуальні ціни. У картках також зазначені склад і вага, щоб вам було легше обрати страву.</p>
        <p><a class="text-link" href="#menu">Переглянути меню →</a></p>
      </section>
      <section aria-labelledby="seo-order-title">
        <h3 id="seo-order-title">Як замовити суші в Гадячі</h3>
        <p>Оформіть замовлення на сайті, вкажіть контактний номер, дату та зручний час отримання. Для самовивозу замовлення можна забрати за адресою: <?=h(location_text($site))?>. Умови доставки адміністратор підтвердить під час зв’язку.</p>
        <?php if($orderHours=order_hours_text($site)):?><p>Онлайн-замовлення приймаємо щодня з <?=h($orderHours)?>. Після надсилання ми зв’яжемося з вами, щоб підтвердити склад замовлення та час готовності.</p><?php endif;?>
        <p><a class="text-link" href="/delivery.html">Доставка та самовивіз →</a></p>
      </section>
    </div>
  </section>
</main>
<?php page_end(true); ?>
