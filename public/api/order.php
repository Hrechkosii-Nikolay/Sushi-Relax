<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

function order_client_ip(): string
{
    foreach ([$_SERVER['HTTP_CF_CONNECTING_IP'] ?? '', $_SERVER['REMOTE_ADDR'] ?? ''] as $candidate) {
        $candidate = trim((string) $candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) return $candidate;
    }
    return 'Невідомо';
}

function order_meta_value(mixed $value, string $fallback = 'Невідомо'): string
{
    if (!is_scalar($value)) return $fallback;
    $clean = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    return $clean === '' ? $fallback : mb_substr($clean, 0, 120);
}

function order_geo_data(string $ip): array
{
    $fallback = ['country'=>'Невідомо', 'city'=>'Невідомо', 'isp'=>'Невідомо'];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return $fallback;
    $ch = curl_init('https://ipwho.is/' . rawurlencode($ip));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>2, CURLOPT_TIMEOUT=>4, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($response) || $status !== 200) return $fallback;
    $data = json_decode($response, true);
    if (!is_array($data) || ($data['success'] ?? false) !== true) return $fallback;
    return [
        'country'=>order_meta_value($data['country'] ?? null),
        'city'=>order_meta_value($data['city'] ?? null),
        'isp'=>order_meta_value($data['connection']['isp'] ?? null),
    ];
}

function order_device_type(string $userAgent): string
{
    if (preg_match('/iPad|Tablet/i', $userAgent)) return 'Планшет';
    if (preg_match('/Mobile|Android|iPhone/i', $userAgent)) return 'Смартфон';
    return 'ПК';
}

function order_os(string $userAgent): string
{
    foreach (['Windows NT'=>'Windows', 'Android'=>'Android', 'iPhone'=>'iOS', 'iPad'=>'iPadOS', 'Mac OS X'=>'macOS', 'Linux'=>'Linux'] as $needle=>$label) {
        if (stripos($userAgent, $needle) !== false) return $label;
    }
    return 'Невідомо';
}

function order_browser(string $userAgent): string
{
    foreach (['/Edg\/([\d.]+)/'=>'Edge', '/OPR\/([\d.]+)/'=>'Opera', '/Chrome\/([\d.]+)/'=>'Chrome', '/Firefox\/([\d.]+)/'=>'Firefox', '/Version\/([\d.]+).*Safari/'=>'Safari'] as $pattern=>$label) {
        if (preg_match($pattern, $userAgent, $match)) return $label . ' ' . $match[1];
    }
    return 'Невідомо';
}

function order_duration_label(int $seconds): string
{
    $seconds = max(0, min(86400, $seconds));
    $minutes = intdiv($seconds, 60);
    return $minutes > 0 ? "{$minutes} хв " . ($seconds % 60) . ' с' : "{$seconds} с";
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json(['error' => 'Метод не підтримується.'], 405);
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 30000) send_json(['error' => 'Запит завеликий.'], 413);
try { $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); } catch (Throwable) { send_json(['error' => 'Некоректний формат запиту.'], 400); }
if (!is_array($input) || !empty($input['website'])) send_json(['error' => 'Запит відхилено.'], 400);
$requestId = preg_replace('/[^a-zA-Z0-9-]/', '', (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
if ($requestId === '' || strlen($requestId) > 80) send_json(['error' => 'Не вдалося підтвердити унікальність замовлення. Оновіть сторінку.'], 400);

$requestIp = order_client_ip();
$isLocalRequest = in_array($requestIp, ['127.0.0.1', '::1'], true);
if (!$isLocalRequest && !acquire_rate_limit('order:' . $requestIp, 5, 600)) send_json(['error' => 'Забагато спроб. Зачекайте перед повторним надсиланням.'], 429);

$site = settings();
if (($site['orders']['manual_enabled'] ?? false) !== true) send_json(['error' => 'Онлайн-замовлення зараз призупинено. Меню залишається доступним.'], 503);
$name = trim((string) ($input['name'] ?? ''));
$phone = preg_replace('/[^0-9+()\- ]/u', '', (string) ($input['phone'] ?? ''));
$persons = filter_var($input['persons'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>20]]);
$fulfillment = (string) ($input['fulfillment'] ?? '');
$orderSource = ($input['source'] ?? '') === 'qr' ? 'qr' : 'site';
$isQrOrder = $orderSource === 'qr' && $fulfillment === 'dinein';
$tableNumber = filter_var($input['table_number'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>999]]);
$address = trim((string) ($input['address'] ?? ''));
$date = (string) ($input['date'] ?? ''); $time = (string) ($input['time'] ?? '');
$comment = trim((string) ($input['comment'] ?? ''));
$timeOnPage = filter_var($input['time_on_page_seconds'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0, 'max_range'=>86400]]);
if ($timeOnPage === false) $timeOnPage = 0;
if (mb_strlen($name) < 2 || mb_strlen($name) > 80) send_json(['error' => 'Перевірте ім’я.'], 422);
if (strlen(preg_replace('/\D/', '', $phone)) < 9 || mb_strlen($phone) > 30) send_json(['error' => 'Перевірте номер телефону.'], 422);
if ($persons === false) send_json(['error' => 'Оберіть кількість персон від 1 до 20.'], 422);
if (!in_array($fulfillment, ['pickup', 'dinein', 'delivery'], true)) send_json(['error' => 'Оберіть спосіб отримання.'], 422);
if ($orderSource === 'qr' && $fulfillment !== 'dinein') send_json(['error' => 'Замовлення з QR-меню оформлюється лише в закладі.'], 422);
if ($isQrOrder && $tableNumber === false) send_json(['error' => 'Вкажіть номер столу.'], 422);
if ($fulfillment === 'delivery' && (mb_strlen($address) < 5 || mb_strlen($address) > 180)) send_json(['error' => 'Перевірте адресу доставки.'], 422);
if (mb_strlen($comment) > 500) send_json(['error' => 'Коментар надто довгий.'], 422);
if (($input['privacy'] ?? false) !== true) send_json(['error' => 'Потрібна згода з політикою конфіденційності.'], 422);
$timezone = new DateTimeZone('Europe/Kyiv');
$orderAt = $isQrOrder ? new DateTimeImmutable('now', $timezone) : DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, $timezone);
if (!$isQrOrder) {
    if (!$orderAt || $orderAt < new DateTimeImmutable('today', $timezone) || $orderAt > new DateTimeImmutable('+' . (int)($site['orders']['days_ahead'] ?? 7) . ' days 23:59', $timezone)) send_json(['error' => 'Оберіть доступні дату та час.'], 422);
    $dateOnly = DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
    if (!$dateOnly || !in_array($time, available_slots($site, $dateOnly), true)) send_json(['error' => 'Обраний час уже недоступний. Оновіть список.'], 422);
    $deliveryEarliest = (string)($site['delivery']['earliest_time'] ?? '11:00');
    if ($fulfillment === 'delivery' && preg_match('/^\d{2}:\d{2}$/', $deliveryEarliest) && $time < $deliveryEarliest) send_json(['error' => 'Доставку можна замовити не раніше ' . $deliveryEarliest . '.'], 422);
}

$items = $input['items'] ?? [];
try{$quote=calculate_order_quote($items,read_json('catalog.json'),$site,$fulfillment,(string)($input['promo_code']??''),$requestId);}catch(InvalidArgumentException $exception){send_json(['error'=>$exception->getMessage()],422);}
$verified=$quote['items'];$total=$quote['subtotal_minor'];$discount=$quote['discount_minor'];$gift=$quote['gift'];$deliveryFee=$quote['delivery_minor'];$grandTotal=$quote['total_minor'];$totalWeight=$quote['weight_g'];
$orderId = bin2hex(random_bytes(12));
$orderNumber = str_pad((string) (hexdec(substr(hash('sha256', $orderId), 0, 12)) % 10000000), 7, '0', STR_PAD_LEFT);
$createdAt = (new DateTimeImmutable())->format('d.m.Y H:i');
$ip = $requestIp;
$geo = order_geo_data($ip);
$userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
$fulfillmentTitle = [
    'pickup' => '📦 САМОВИВІЗ',
    'dinein' => '🍽 У ЗАКЛАДІ',
    'delivery' => '🛵 ДОСТАВКА',
][$fulfillment];
$lines = ["{$fulfillmentTitle} #{$orderNumber}"];
if ($isQrOrder) $lines[] = "🪑 Стіл № {$tableNumber}";
$lines = array_merge($lines, [
    '━━━━━━━━━━━━━━',
    '',
    "👤 Ім’я: {$name}",
    "☎️ Телефон: {$phone}",
    "🍽 Кількість персон: {$persons}",
]);
if ($fulfillment === 'delivery') {
    $lines[] = "🏠 Адреса доставки: {$address}";
}
if (!$isQrOrder) {
    if ($fulfillment === 'pickup') $lines[] = '💳 Оплата: ПЕРЕДПЛАТА 50% після підтвердження замовлення';
    if ($fulfillment === 'delivery') $lines[] = '💳 Оплата: ПЕРЕДПЛАТА після підтвердження замовлення';
    $lines[] = "📅 Дата: {$orderAt->format('d.m.Y')}";
    $lines[] = '⏰ Час: ' . slot_label($site,$time);
}
$lines[] = '';
$lines[] = '━━━━━━━━━━━━━━';
$lines[] = '🍣 СКЛАД ЗАМОВЛЕННЯ';
foreach ($verified as $row) {
    $weightLabel=is_int($row['weight_g']??null)?' · '.$row['weight_g'].' г/шт':'';
    $lines[] = "• {$row['quantity']} × {$row['name']} · " . money($row['unit_minor']) . "/шт{$weightLabel} - " . money($row['line_minor']);
    foreach ($row['options'] as $option) $lines[] = "  ➕ {$option['name']} - " . money($option['price_minor']);
}
if (is_int($totalWeight)) $lines[] = "⚖️ Загальна вага: {$totalWeight} г";
$lines[] = '';
$lines[] = '💵 Сума страв: ' . money($total);
if ($quote['promo_code']) $lines[] = '🏷 Промокод: ' . $quote['promo_code'];
if ($discount) $lines[] = '🔻 Знижка: −' . money($discount);
if ($gift) $lines[] = '🎁 Подарунок: ' . $gift['name'] . ' - 0 ₴';
if ($fulfillment === 'delivery') $lines[] = '🛵 Доставка: у межах міста безкоштовна; за межі міста - таксі за тарифом перевізника';
if (!$isQrOrder) $lines[] = '💳 РАЗОМ: ' . money($grandTotal);
if ($comment !== '') {
    $lines[] = '';
    $lines[] = "💬 Коментар:\n{$comment}";
}
$customerSummary = implode("\n", array_slice($lines, 1));
$lines[] = '';
$lines[] = '━━━━━━━━━━━━━━';
if ($isQrOrder) {
    $lines[] = '🏙 Місто: ' . $geo['city'];
    $lines[] = "🌐 IP: {$ip}";
} else {
    $lines[] = '🌍 Країна: ' . $geo['country'];
    $lines[] = '🏙 Місто: ' . $geo['city'];
    $lines[] = '📡 Провайдер: ' . $geo['isp'];
    $lines[] = "🌐 IP: {$ip}";
    $lines[] = '💻 Тип: ' . order_device_type($userAgent);
    $lines[] = '📱 ОС: ' . order_os($userAgent);
    $lines[] = '🖥 Браузер: ' . order_browser($userAgent);
    $lines[] = '⏱ На сторінці: ' . order_duration_label((int) $timeOnPage);
    $lines[] = '';
    $lines[] = "🕒 Створено: {$createdAt}";
}
$message = implode("\n", $lines);
if(mb_strlen($message)>3900)send_json(['error'=>'Замовлення завелике для надсилання. Зменште кількість різних позицій або скоротіть коментар.'],422);

$token = app_secret('SUSHI_TELEGRAM_BOT_TOKEN','telegram_bot_token');
$chatId = app_secret('SUSHI_TELEGRAM_CHAT_ID','telegram_chat_id');
if ($token === '' || $chatId === '') send_json(['error' => 'Надсилання замовлень ще не налаштоване адміністратором.'], 503);
if (!reserve_idempotency($requestId, $orderId, $orderNumber, hash('sha256',$raw))) {
    $existing=read_idempotency($requestId);
    if(($existing['status']??'')==='delivered'&&!empty($existing['order_number'])&&hash_equals((string)($existing['payload_hash']??''),hash('sha256',$raw)))send_json(['ok'=>true,'order_number'=>$existing['order_number'],'fulfillment'=>$fulfillment,'source'=>$orderSource,'summary'=>$customerSummary,'duplicate'=>true]);
    send_json(['error' => 'Це замовлення вже обробляють. Не надсилайте його повторно одразу; якщо підтвердження не з’явиться, зв’яжіться із закладом.'], 409);
}
if($quote['promo_code']&&!reserve_single_use_promo($quote['promo_code'],$requestId,$orderId)){
    release_idempotency($requestId,$orderId);
    send_json(['error'=>'Цей промокод уже використано. Оберіть інший код.'],422);
}

$ch = curl_init('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage');
curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['chat_id'=>$chatId,'text'=>$message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
$response = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); $curlErrorCode = curl_errno($ch); curl_close($ch);
$decoded = is_string($response) ? json_decode($response, true) : null;
if ($error !== '') {
    technical_log('telegram.ambiguous', ['http_status'=>$status, 'curl_error_code'=>$curlErrorCode]);
    if($quote['promo_code']&&!use_single_use_promo($quote['promo_code'],$requestId,$orderId,$orderNumber))technical_log('promocode.consume_failed',['order_number'=>$orderNumber]);
    send_json(['error' => 'Немає однозначного підтвердження доставки замовлення. Кошик не очищено. Щоб уникнути дубля, не повторюйте одразу - зв’яжіться із закладом.'], 502);
}
if ($status !== 200 || !is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
    technical_log('telegram.rejected', ['http_status'=>$status, 'api_ok'=>is_array($decoded) ? (bool)($decoded['ok'] ?? false) : null]);
    if($quote['promo_code'])release_single_use_promo($quote['promo_code'],$requestId,$orderId);
    release_idempotency($requestId,$orderId);
    send_json(['error' => 'Не вдалося надіслати замовлення. Кошик не очищено - спробуйте пізніше або зв’яжіться із закладом.'], 502);
}
if($quote['promo_code']&&!use_single_use_promo($quote['promo_code'],$requestId,$orderId,$orderNumber))technical_log('promocode.consume_failed',['order_number'=>$orderNumber]);
if (!mark_idempotency_delivered($requestId,$orderId)) technical_log('idempotency.mark_failed');
send_json(['ok'=>true,'order_number'=>$orderNumber,'fulfillment'=>$fulfillment,'source'=>$orderSource,'summary'=>$customerSummary]);
