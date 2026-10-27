<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const PROMO_DATABASE = APP_ROOT . '/data/promocodes.json';
const PROMO_EXPORT = APP_ROOT . '/promo.txt';

if (is_file(PROMO_DATABASE) && !in_array('--force', $argv, true)) {
    fwrite(STDERR, "data/promocodes.json уже існує. Використайте --force лише для свідомої повної заміни кодів.\n");
    exit(1);
}

$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
$discounts = [5, 10, 15, 20];
$seen = [];
$rows = [];
$export = [
    'Sushi Relax - одноразові промокоди',
    'Після дефіса вказано відсоток знижки.',
    '',
];

foreach ($discounts as $discount) {
    $export[] = "=== {$discount}% ===";
    for ($index = 0; $index < 100; $index++) {
        do {
            $prefix = '';
            for ($char = 0; $char < 4; $char++) $prefix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = "{$prefix}-{$discount}";
        } while (isset($seen[$code]));
        $seen[$code] = true;
        $rows[] = [
            'code' => $code,
            'type' => 'percent',
            'value' => $discount,
            'minimum_minor' => 0,
            'start' => null,
            'end' => null,
            'active' => true,
            'reserved_request_id' => null,
            'reserved_order_id' => null,
            'reserved_at' => null,
            'used_at' => null,
            'used_request_id' => null,
            'used_order_number' => null,
        ];
        $export[] = $code;
    }
    $export[] = '';
}

$database = [
    'schema' => 1,
    'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format(DATE_ATOM),
    'promocodes' => $rows,
];
$json = json_encode($database, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";

if (file_put_contents(PROMO_DATABASE, $json, LOCK_EX) === false || file_put_contents(PROMO_EXPORT, implode("\n", $export) . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "Не вдалося записати промокоди.\n");
    exit(1);
}
@chmod(PROMO_DATABASE, 0640);
@chmod(PROMO_EXPORT, 0644);
echo 'Створено ' . count($rows) . " унікальних одноразових промокодів.\n";
