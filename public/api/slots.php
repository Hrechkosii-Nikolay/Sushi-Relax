<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

$dateValue = (string) ($_GET['date'] ?? '');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue, new DateTimeZone('Europe/Kyiv'));
if (!$date || $date->format('Y-m-d') !== $dateValue) send_json(['error' => 'Некоректна дата.'], 422);
$site = settings();
$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Kyiv'));
$daysAhead = max(0, (int) ($site['orders']['days_ahead'] ?? 7));
$slots=available_slots($site,$date);
send_json([
    'date' => $dateValue,
    'slots' => $slots,
    'slot_options' => array_map(static fn(string $slot): array => ['value'=>$slot,'label'=>slot_label($site,$slot)],$slots),
    'orders_enabled' => ($site['orders']['manual_enabled'] ?? false) === true,
    'min_date' => $today->format('Y-m-d'),
    'max_date' => $today->modify('+' . $daysAhead . ' days')->format('Y-m-d'),
]);
