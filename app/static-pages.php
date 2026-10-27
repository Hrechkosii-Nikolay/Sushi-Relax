<?php
declare(strict_types=1);

require_once __DIR__ . '/view.php';

function write_static_html(string $relativePath, callable $renderer): void
{
    ob_start();
    $renderer();
    $html = ob_get_clean();
    if (!is_string($html) || !str_starts_with(ltrim($html), '<!doctype html>')) {
        throw new RuntimeException('Не вдалося сформувати ' . $relativePath);
    }
    $target = APP_ROOT . '/public/' . ltrim($relativePath, '/');
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Не вдалося створити каталог для ' . $relativePath);
    }
    $tmp = tempnam($directory, '.static-');
    if ($tmp === false || file_put_contents($tmp, $html, LOCK_EX) === false || !rename($tmp, $target)) {
        @unlink((string)$tmp);
        throw new RuntimeException('Не вдалося записати ' . $relativePath);
    }
    chmod($target, 0644);
}

function build_static_content_pages(): void
{
    $site = settings();
    $catalog = catalog();

    write_static_html('about.html', static function () use ($site): void {
        page_start('Sushi Relax - суші в Гадячі | Про заклад', 'Sushi Relax - затишний суші-бар у Гадячі. Роли, сети, гарячі страви, самовивіз і доставка. Завітайте за адресою: вул. Соборна, 13а.', '', '/about.html'); ?>
<main id="content" class="content-page"><p class="eyebrow">Суші-бар у Гадячі</p><h1>Про<br>Sushi Relax</h1><p class="lead">Sushi Relax - місце для тих, хто шукає найкращі суші в Гадячі, приємну атмосферу та зручний формат замовлення. Тут можна зустрітися з друзями, провести затишний вечір або замовити улюблені роли додому.</p><div class="info-grid"><article class="info-card"><h2>Наша атмосфера</h2><p>Тепле освітлення, сучасний інтер’єр і спокійна музика створюють простір, у якому хочеться відпочити та насолодитися вечором. Sushi Relax підходить для дружніх зустрічей, побачень і сімейного відпочинку.</p></article><article class="info-card"><h2>Наше меню</h2><p>У меню Sushi Relax представлені класичні та авторські роли, запечені роли, сети, гарячі страви, солодкі роли й напої. Зручний каталог допомагає швидко переглянути склад, вагу, фото та актуальну ціну кожної позиції.</p></article><article class="info-card"><h2>Зручне замовлення</h2><p>Замовити суші в Гадячі можна безпосередньо на сайті. Оберіть страви, додайте їх до кошика та вкажіть самовивіз або доставку. У закладі доступне QR-меню для самостійного оформлення замовлення зі столу.</p></article><article class="info-card"><h2>Чому Sushi Relax</h2><p>Ми поєднуємо різноманітне меню, виразні смаки та гостинну атмосферу. Наша мета - зробити кожне замовлення приємною частиною вашого дня, незалежно від того, вечеряєте ви в закладі чи обираєте суші із собою.</p></article><article class="info-card about-location-card"><h2>Завітайте до нас</h2><p>Sushi Relax розташований за адресою: <?=h(location_text($site))?>. Переглядайте меню онлайн, бронюйте столик на головній сторінці та стежте за новинами й актуальними пропозиціями в Instagram.</p><p><a class="text-link" href="/#menu">Переглянути меню →</a></p></article></div></main>
<?php page_end(true);
    });

    write_static_html('contacts.html', static function () use ($site): void {
        page_start('Контакти - Sushi Relax', 'Адреса, телефон і графік роботи Sushi Relax.', '', '/contacts.html'); ?>
<main id="content" class="content-page"><p class="eyebrow">Зв’яжіться з нами</p><h1>Контакти</h1><div class="info-grid contacts-grid">
  <article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Адреса</span></h2><p><?=h(location_text($site))?></p></article>
  <article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 3.5 9 8.1 6.9 9.8a15.3 15.3 0 0 0 7.3 7.3l1.7-2.1 4.6 2.4-.8 3.1c-.2.8-1 1.4-1.8 1.4C9.2 21.9 2.1 14.8 2.1 6.1c0-.8.6-1.6 1.4-1.8l3.1-.8Z"/></svg><span>Телефон</span></h2><?php if($site['phone']??null):phone_actions($site);else:?><p>Буде доданий після підтвердження.</p><?php endif;?></article>
  <article class="info-card info-card--wide"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><span>Графік</span></h2><?php weekly_hours($site);?><?php if($orderHours=order_hours_text($site)):?><p class="form-note">Замовлення приймаємо щодня: <?=h($orderHours)?></p><?php endif;?></article>
  <article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg><span>Instagram</span></h2><p><?php $instagram=normalized_instagram_url($site['instagram_url']??null);?><?=$instagram?'<a class="text-link" href="'.h($instagram).'" target="_blank" rel="noopener noreferrer">@'.h(basename(rtrim($instagram,'/'))).' ↗</a>':'Профіль ще не вказаний.'?></p></article>
  <article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-7.8 18-3.4-7-6.8-3.1L21 3Z"/><path d="m9.8 14 4.2-4"/></svg><span>Telegram</span></h2><p><?php $telegram=normalized_telegram_url($site['telegram_contact']??null);?><?=$telegram?'<a class="text-link" href="'.h($telegram).'" target="_blank" rel="noopener noreferrer">Написати в Telegram</a>':'Контакт закладу ще не вказаний.'?></p></article>
</div></main>
<?php page_end(true);
    });

    write_static_html('reservation.html', static function () use ($site): void {
        $timezone = new DateTimeZone('Europe/Kyiv');
        $today = new DateTimeImmutable('today', $timezone);
        $maxDate = $today->modify('+' . max(0, (int)($site['orders']['days_ahead'] ?? 7)) . ' days');
        page_start('Бронювання столу - Sushi Relax', 'Забронюйте стіл у Sushi Relax онлайн без попереднього замовлення.', 'reservation-mode', '/reservation.html'); ?>
<main id="content" class="reservation-page"><header class="reservation-heading"><p class="eyebrow">Без попереднього замовлення</p><h1>Забронювати<br><em>стіл</em></h1><p class="lead">Вкажіть контакти, кількість гостей і зручний час. Замовлення одразу надійде адміністратору, який зв’яжеться з вами для підтвердження.</p></header><div class="reservation-layout"><form id="reservation-form" class="reservation-form" aria-label="Форма бронювання столу" data-min-date="<?=h($today->format('Y-m-d'))?>" data-max-date="<?=h($maxDate->format('Y-m-d'))?>" novalidate><fieldset class="form-grid"><legend class="sr-only">Дані для бронювання</legend><label class="field"><span>Ім’я</span><input name="name" autocomplete="name" maxlength="80" required></label><label class="field"><span>Телефон</span><input name="phone" type="tel" autocomplete="tel" inputmode="tel" placeholder="+380 XX XXX XX XX" maxlength="30" required></label><label class="field"><span>Кількість гостей</span><select name="persons" required><option value="">Оберіть</option><?php for($persons=1;$persons<=20;$persons++):?><option value="<?=$persons?>"><?=$persons?></option><?php endfor;?></select></label><label class="field"><span>Дата</span><input name="date" type="date" required></label><label class="field field--full"><span>Час</span><select name="time" required disabled><option value="">Спочатку оберіть дату</option></select><small>Бронювання підтверджується адміністратором після отримання замовлення.</small></label><label class="field field--full"><span>Коментар <small>(необов’язково)</small></span><textarea name="comment" maxlength="500" placeholder="Наприклад: дитячий стілець або святкування"></textarea></label><label class="honeypot" aria-hidden="true">Не заповнюйте<input name="website" tabindex="-1" autocomplete="off"></label><label class="consent field--full"><input name="privacy" type="checkbox" required><span>Погоджуюся з <a class="text-link" href="/privacy.html" target="_blank">політикою конфіденційності</a>.</span></label></fieldset><div class="reservation-actions"><button class="button" type="submit" data-submit-reservation>Надіслати замовлення</button><p class="status-message" data-reservation-status role="status" aria-live="polite"></p></div></form><aside class="reservation-aside"><p class="eyebrow">Sushi Relax</p><h2>Чекаємо на вас</h2><p><?=h(location_text($site))?></p><?php if($site['phone']??null):phone_actions($site);endif;?><p>Надсилання форми ще не є остаточним підтвердженням столу - дочекайтеся відповіді адміністратора.</p></aside></div><section class="reservation-success" aria-label="Підтвердження бронювання" data-reservation-success tabindex="-1" hidden><p class="eyebrow">Sushi Relax</p><h2>Чекаємо на вас</h2><p><?=h(location_text($site))?></p><?php if($site['phone']??null):phone_actions($site);endif;?><p class="reservation-success-time">Ваше бронювання: <strong data-reservation-datetime>-</strong></p><p>Адміністратор отримав замовлення та зв’яжеться з вами для підтвердження.</p><a class="button" href="/">На головну</a></section></main>
<?php page_end(false, ['/assets/js/reservation.js?v=20261026-3']);
    });

    write_static_html('delivery.html', static function () use ($site): void {
        page_start('Доставка та оплата - Sushi Relax', 'Умови доставки, самовивозу та оплати Sushi Relax.', '', '/delivery.html'); ?>
<main id="content" class="content-page"><p class="eyebrow">Важлива інформація</p><h1 class="delivery-title"><span>Доставка</span><span>та оплата</span></h1><p class="lead">Для доставки та самовивозу діє передплата.</p><div class="info-grid"><article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg><span>Доставка</span></h2><?php if(delivery_configured($site)):?><p>Мінімальна сума - <?=h(money($site['delivery']['minimum_minor']))?>. Вартість доставки - <?=h(money($site['delivery']['fee_minor']))?><?php if(is_int($site['delivery']['free_from_minor']??null)):?>. Безкоштовна доставка від <?=h(money($site['delivery']['free_from_minor']))?><?php endif;?>.</p><?php else:?><p>Мінімальна сума, вартість і зона доставки ще не налаштовані. До погодження правил доступний самовивіз.</p><?php endif;?><p>Доставляємо всі позиції меню, включно з напоями.</p></article><article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 13H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg><span>Самовивіз</span></h2><p><?=h(location_text($site))?></p></article><article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M16 3v18M16 3c3 2 4 5 4 8h-4"/></svg><span>У закладі</span></h2><p>Оберіть дату, час і кількість персон - ми підготуємо замовлення до вашого приходу. Можливість розміщення підтвердить адміністратор.</p></article><article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg><span>Час</span></h2><p>Дата та доступний час обираються під час оформлення. Деталі підтвердить адміністратор.</p></article><article class="info-card"><h2 class="contact-card-title"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg><span>Оплата</span></h2><p>Доставка та самовивіз - за передплатою. Після підтвердження замовлення адміністратор надасть реквізити та суму до сплати.</p></article></div></main>
<?php page_end(true);
    });

    write_static_html('privacy.html', static function () use ($site): void {
        page_start('Політика конфіденційності - Sushi Relax', 'Інформація про обробку персональних даних на сайті Sushi Relax.', '', '/privacy.html'); ?>
<main id="content" class="content-page"><p class="eyebrow">Документ на погодження</p><h1>Приватність</h1><p class="lead"><?=h($site['privacy_text'])?></p><h2>Які дані використовуються</h2><p>Для оформлення замовлення або бронювання потрібні ім’я, телефон, кількість гостей, дата та час, а для доставки - адреса. Ім’я та телефон можуть зберігатися лише у браузері користувача для зручності. Адреса не зберігається в localStorage.</p><h2>Для чого</h2><p>Дані передаються адміністраторам закладу для зв’язку та підтвердження замовлення. Сервер не створює постійну базу історії замовлень чи бронювань.</p><h2>Ваш вибір</h2><p>Ви можете очистити локально збережені дані через налаштування браузера. Фінальні контакти відповідальної особи будуть додані власником.</p></main>
<?php page_end(true);
    });

    write_static_html('success.html', static function (): void {
        page_start('Дякуємо - Sushi Relax', 'Замовлення Sushi Relax отримано.', 'success-mode', '', false); ?>
<main id="content" class="content-page"><p class="eyebrow">Замовлення отримано</p><h1>Дякуємо!</h1><p class="lead">Ваше замовлення отримано. Найближчим часом з вами зв’яжеться адміністратор для підтвердження.</p><section class="info-card" aria-label="Деталі замовлення" data-success-order><p data-order-fulfillment-row>Спосіб отримання: <strong data-order-fulfillment>-</strong></p><p>Номер замовлення: <strong data-order-number>-</strong></p><div data-order-copy></div></section><p class="success-return"><a class="button" href="/#menu">Повернутися до меню</a></p></main>
<?php page_end(false, ['/assets/js/success.js?v=20261026-3']);
    });

    write_static_html('qr-menu/index.html', static function () use ($catalog): void {
        page_start('QR-меню - Sushi Relax', 'Актуальне меню Sushi Relax із можливістю самостійно оформити замовлення в закладі.', 'qr-mode', '/qr-menu/'); ?>
<main id="content" class="qr-main"><header class="section-heading"><hgroup><p class="eyebrow">У залі</p><h1>QR-меню</h1></hgroup><search><label class="search"><span class="sr-only">Пошук страв</span><input type="search" placeholder="Знайти страву…" data-menu-search></label></search></header><p class="catalog-notice">Додайте страви до кошика та оформіть замовлення самостійно. Замовлення одразу надійде адміністратору.</p><?php if(trim((string)($catalog['notice']??''))!==''):?><p class="catalog-notice"><?=h($catalog['notice'])?></p><?php endif;?><?php category_navigation($catalog);?><div data-menu-catalog><?php menu_catalog($catalog,true);?></div><p class="no-results" data-no-results hidden>Нічого не знайшли. Спробуйте іншу назву.</p></main>
<?php page_end(true);
    });

    $products = array_values($catalog['products'] ?? []);
    $categoryOrder = array_flip(array_map(static fn(array $category): string => (string)($category['id'] ?? ''), $catalog['categories'] ?? []));
    usort($products, static fn(array $left, array $right): int => [($categoryOrder[(string)($left['category'] ?? '')] ?? PHP_INT_MAX), (int)($left['order'] ?? 0)] <=> [($categoryOrder[(string)($right['category'] ?? '')] ?? PHP_INT_MAX), (int)($right['order'] ?? 0)]);
    foreach ($products as $currentIndex => $product) {
        $id = preg_replace('/[^a-z0-9-]+/i', '-', (string)($product['id'] ?? ''));
        if ($id === '') continue;
        write_static_html('products/' . $id . '.html', static function () use ($catalog, $products, $product, $currentIndex): void {
            require APP_ROOT . '/app/product-template.php';
        });
    }
}
