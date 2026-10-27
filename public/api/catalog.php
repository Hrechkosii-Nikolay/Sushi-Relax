<?php
require_once __DIR__ . '/../../app/bootstrap.php';
$catalog = catalog();
header('Cache-Control: public, max-age=60');
send_json($catalog);
