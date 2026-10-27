<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
try { echo 'Створено: ' . backup_root() . '/' . create_data_backup() . PHP_EOL; }
catch(Throwable $exception) { fwrite(STDERR, $exception->getMessage() . PHP_EOL); exit(1); }
