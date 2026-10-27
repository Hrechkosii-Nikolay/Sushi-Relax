<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
build_static_homepage();
echo "Оновлено публічні HTML-сторінки: " . realpath(__DIR__ . '/../public') . PHP_EOL;
if (build_sitemap(settings())) echo "Оновлено: " . realpath(__DIR__ . '/../public/sitemap.xml') . PHP_EOL;
