<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const DATA_ROOT = APP_ROOT . '/data';
const VAR_ROOT = APP_ROOT . '/var';

date_default_timezone_set('Europe/Kyiv');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
$phpLogRoot = VAR_ROOT . '/logs';
if ((is_dir($phpLogRoot) || @mkdir($phpLogRoot, 0750, true)) && is_writable($phpLogRoot)) {
    @ini_set('error_log', $phpLogRoot . '/php-error.log');
}
unset($phpLogRoot);

function app_secret(string $environmentName, string $configKey): string
{
    $environmentValue = getenv($environmentName);
    if (is_string($environmentValue) && $environmentValue !== '') return $environmentValue;
    static $config = null;
    if ($config === null) {
        $path = APP_ROOT . '/config/secrets.php';
        $loaded = is_file($path) ? require $path : [];
        $config = is_array($loaded) ? $loaded : [];
        $telegramPath = APP_ROOT . '/config/telegram-config.php';
        $telegram = is_file($telegramPath) ? require $telegramPath : [];
        if (is_array($telegram)) {
            if (!isset($config['telegram_bot_token']) && isset($telegram['bot_token'])) $config['telegram_bot_token'] = $telegram['bot_token'];
            if (!isset($config['telegram_chat_id']) && isset($telegram['chat_id'])) $config['telegram_chat_id'] = $telegram['chat_id'];
        }
    }
    $value = $config[$configKey] ?? '';
    return is_scalar($value) ? trim((string)$value) : '';
}

function read_json(string $file): array
{
    $path = DATA_ROOT . '/' . $file;
    $raw = is_file($path) ? file_get_contents($path) : false;
    $data = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        throw new RuntimeException("Не вдалося прочитати {$file}");
    }
    return $data;
}

function write_json_atomic(string $file, array $data): void
{
    $path = DATA_ROOT . '/' . $file;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $lockPath = $path . '.lock';
    $lock = fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Не вдалося заблокувати файл даних');
    }
    $tmp = tempnam(dirname($path), '.tmp-');
    if ($tmp === false || file_put_contents($tmp, $json, LOCK_EX) === false) {
        flock($lock, LOCK_UN);
        throw new RuntimeException('Не вдалося записати тимчасовий файл');
    }
    chmod($tmp, 0640);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        flock($lock, LOCK_UN);
        throw new RuntimeException('Не вдалося замінити файл даних');
    }
    flock($lock, LOCK_UN);
    fclose($lock);
}

function backup_root(): string
{
    return getenv('SUSHI_BACKUP_DIR') ?: VAR_ROOT . '/backups';
}

function create_data_backup(): string
{
    $root=backup_root();if(!is_dir($root)&&!mkdir($root,0750,true))throw new RuntimeException('Не вдалося створити каталог резервних копій.');
    $payload=['created_at'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format(DATE_ATOM),'schema'=>1,'files'=>[]];
    foreach(['catalog.json','settings.json']as$file)$payload['files'][$file]=read_json($file);
    if(is_file(DATA_ROOT.'/promocodes.json'))$payload['files']['promocodes.json']=read_json('promocodes.json');
    $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    $filename='sushi-relax-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';$target=$root.'/'.$filename;$tmp=tempnam($root,'.tmp-');
    if($tmp===false||file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$target)){@unlink((string)$tmp);throw new RuntimeException('Не вдалося записати резервну копію.');}
    chmod($target,0640);$check=json_decode((string)file_get_contents($target),true);
    if(!is_array($check)||!isset($check['files']['catalog.json'],$check['files']['settings.json'])){@unlink($target);throw new RuntimeException('Перевірка резервної копії не пройдена.');}
    $backups=glob($root.'/sushi-relax-*.json')?:[];rsort($backups);foreach(array_slice($backups,30)as$old)@unlink($old);
    return $filename;
}

function list_data_backups(): array
{
    $files=glob(backup_root().'/sushi-relax-*.json')?:[];rsort($files);$result=[];
    foreach($files as$path){$name=basename($path);if(!preg_match('/^sushi-relax-\d{8}-\d{6}-[a-f0-9]{6}\.json$/',$name))continue;$data=json_decode((string)@file_get_contents($path),true);if(!is_array($data)||($data['schema']??null)!==1||!isset($data['files']['catalog.json'],$data['files']['settings.json']))continue;$result[]=['name'=>$name,'created_at'=>$data['created_at']??null,'size'=>(int)filesize($path)];}
    return $result;
}

function restore_data_backup(string $filename): void
{
    if(!preg_match('/^sushi-relax-\d{8}-\d{6}-[a-f0-9]{6}\.json$/',$filename))throw new RuntimeException('Некоректне ім’я копії.');
    $root=realpath(backup_root());$path=$root!==false?realpath($root.'/'.$filename):false;
    if($root===false||$path===false||dirname($path)!==$root)throw new RuntimeException('Резервну копію не знайдено.');
    $data=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);$catalog=$data['files']['catalog.json']??null;$settings=$data['files']['settings.json']??null;
    if(!is_array($catalog)||!is_array($settings)||!is_array($catalog['categories']??null)||!is_array($catalog['products']??null)||!isset($settings['orders'],$settings['delivery']))throw new RuntimeException('Структура копії не пройшла перевірку.');
    create_data_backup();write_json_atomic('catalog.json',$catalog);write_json_atomic('settings.json',$settings);if(is_array($data['files']['promocodes.json']??null))write_json_atomic('promocodes.json',$data['files']['promocodes.json']);build_static_homepage();
}

function cleanup_runtime_files(): void
{
    $root=VAR_ROOT.'/runtime';if(!is_dir($root))return;$now=time();foreach(glob($root.'/*')?:[]as$file){$age=$now-(int)@filemtime($file);if((str_contains(basename($file),'idempotency-')&&$age>7200)||(str_contains(basename($file),'rate-')&&$age>86400))@unlink($file);}
}

/**
 * Записує лише технічні коди та безпечні метадані. Персональні дані,
 * текст замовлення, Telegram-відповідь і значення секретів сюди не передаються.
 */
function technical_log(string $event, array $context = []): void
{
    $event = preg_replace('/[^a-z0-9_.-]/i', '-', $event) ?: 'unknown';
    $safe = [];
    foreach (array_slice($context, 0, 12, true) as $key => $value) {
        if (!preg_match('/^[a-z0-9_.-]{1,40}$/i', (string)$key) || (!is_scalar($value) && $value !== null)) continue;
        $safe[(string)$key] = is_string($value) ? mb_substr($value, 0, 160) : $value;
    }
    $root = VAR_ROOT . '/logs';
    if (!is_dir($root) && !@mkdir($root, 0750, true)) return;
    $path = $root . '/technical.log';
    if (is_file($path) && (int)@filesize($path) > 524288) {
        @unlink($path . '.1');
        @rename($path, $path . '.1');
    }
    $line = json_encode([
        'at'=>(new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format(DATE_ATOM),
        'event'=>$event,
        'context'=>$safe,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($line)) @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    if (is_file($path)) @chmod($path, 0640);
}

function acquire_rate_limit(string $key, int $limit, int $windowSeconds): bool
{
    $root=VAR_ROOT.'/runtime';if(!is_dir($root)&&!mkdir($root,0750,true))return false;if(random_int(1,50)===1)cleanup_runtime_files();
    $path=$root.'/rate-'.hash('sha256',$key);$handle=fopen($path,'c+');if(!$handle||!flock($handle,LOCK_EX))return false;$raw=stream_get_contents($handle);$data=is_string($raw)?json_decode($raw,true):null;$now=time();
    if(!is_array($data)||($data['reset']??0)<=$now)$data=['count'=>0,'reset'=>$now+$windowSeconds];$allowed=$data['count']<$limit;if($allowed)$data['count']++;
    ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode($data));fflush($handle);flock($handle,LOCK_UN);fclose($handle);chmod($path,0640);return$allowed;
}

function idempotency_path(string $requestId): string { return VAR_ROOT.'/runtime/idempotency-'.hash('sha256',$requestId); }
function read_idempotency(string $requestId): ?array
{
    $path=idempotency_path($requestId);$handle=@fopen($path,'r');if(!$handle||!flock($handle,LOCK_SH)){if($handle)fclose($handle);return null;}$raw=stream_get_contents($handle);flock($handle,LOCK_UN);fclose($handle);$data=is_string($raw)?json_decode($raw,true):null;return is_array($data)?$data:null;
}
function reserve_idempotency(string $requestId, string $orderId, string $orderNumber, string $payloadHash): bool
{
    $root=VAR_ROOT.'/runtime';if(!is_dir($root)&&!mkdir($root,0750,true))return false;if(random_int(1,50)===1)cleanup_runtime_files();$path=idempotency_path($requestId);
    if(is_file($path)&&time()-(int)@filemtime($path)>7200)@unlink($path);
    $handle=@fopen($path,'x');if(!$handle)return false;$payload=['order_id'=>$orderId,'order_number'=>$orderNumber,'payload_hash'=>$payloadHash,'created_at'=>time(),'status'=>'pending'];fwrite($handle,json_encode($payload));fflush($handle);fclose($handle);chmod($path,0640);return true;
}
function mark_idempotency_delivered(string $requestId, string $orderId): bool
{
    $path=idempotency_path($requestId);$handle=@fopen($path,'c+');if(!$handle||!flock($handle,LOCK_EX)){if($handle)fclose($handle);return false;}$raw=stream_get_contents($handle);$data=is_string($raw)?json_decode($raw,true):null;if(!is_array($data)||!hash_equals((string)($data['order_id']??''),$orderId)){flock($handle,LOCK_UN);fclose($handle);return false;}$data['status']='delivered';$data['delivered_at']=time();ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode($data));fflush($handle);flock($handle,LOCK_UN);fclose($handle);return true;
}
function release_idempotency(string $requestId, string $orderId): void
{
    $path=idempotency_path($requestId);$handle=@fopen($path,'r+');if(!$handle||!flock($handle,LOCK_EX)){if($handle)fclose($handle);return;}$raw=stream_get_contents($handle);$data=is_string($raw)?json_decode($raw,true):null;if(is_array($data)&&hash_equals((string)($data['order_id']??''),$orderId)&&($data['status']??'')==='pending')@unlink($path);flock($handle,LOCK_UN);fclose($handle);
}

function catalog(): array
{
    $catalog = read_json('catalog.json');
    usort($catalog['categories'], static fn(array $a, array $b): int => ((int)($a['order'] ?? 0)) <=> ((int)($b['order'] ?? 0)));
    $catalog['products'] = array_values(array_filter(
        $catalog['products'] ?? [],
        static fn(array $product): bool => ($product['available'] ?? false) === true
    ));
    usort($catalog['products'], static fn(array $a, array $b): int => [($a['category'] ?? ''), (int)($a['order'] ?? 0)] <=> [($b['category'] ?? ''), (int)($b['order'] ?? 0)]);
    return $catalog;
}

function settings(): array
{
    return read_json('settings.json');
}

function promocode_rules(array $site): array
{
    $rules=[];
    foreach($site['promocodes']??[]as$promo)if(is_array($promo)&&trim((string)($promo['code']??''))!=='')$rules[mb_strtoupper(trim((string)$promo['code']))]=$promo;
    $path=DATA_ROOT.'/promocodes.json';
    if(is_file($path)){
        $database=read_json('promocodes.json');
        foreach($database['promocodes']??[]as$promo)if(is_array($promo)&&trim((string)($promo['code']??''))!=='')$rules[mb_strtoupper(trim((string)$promo['code']))]=$promo;
    }
    return array_values($rules);
}

function promocode_is_spent(array $promo,string $requestId=''): bool
{
    $usedRequest=(string)($promo['used_request_id']??'');
    if(!empty($promo['used_at']))return $requestId===''||$usedRequest===''||!hash_equals($usedRequest,$requestId);
    $reservedRequest=(string)($promo['reserved_request_id']??'');
    return $reservedRequest!==''&&($requestId===''||!hash_equals($reservedRequest,$requestId));
}

function write_promocode_database_locked(string $path,array $database): bool
{
    try{$json=json_encode($database,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";}catch(Throwable){return false;}
    $tmp=tempnam(dirname($path),'.tmp-promo-');
    if($tmp===false||file_put_contents($tmp,$json,LOCK_EX)===false){if(is_string($tmp))@unlink($tmp);return false;}
    @chmod($tmp,0640);
    if(!@rename($tmp,$path)){@unlink($tmp);return false;}
    return true;
}

function mutate_single_use_promo(string $code,string $requestId,string $orderId,string $orderNumber,string $action): bool
{
    $code=mb_strtoupper(trim($code));
    if($code==='')return true;
    $path=DATA_ROOT.'/promocodes.json';
    if(!is_file($path))return true;
    $lock=@fopen($path.'.lock','c');
    if(!$lock||!flock($lock,LOCK_EX)){if($lock)fclose($lock);return false;}
    $raw=@file_get_contents($path);$database=is_string($raw)?json_decode($raw,true):null;$found=false;$changed=false;$allowed=true;$databaseValid=is_array($database)&&is_array($database['promocodes']??null);
    if(!$databaseValid)$allowed=false;
    if($allowed)foreach($database['promocodes']as&$promo){
        if(!is_array($promo)||mb_strtoupper((string)($promo['code']??''))!==$code)continue;
        $found=true;$usedRequest=(string)($promo['used_request_id']??'');$reservedRequest=(string)($promo['reserved_request_id']??'');
        if($action==='reserve'){
            if(!empty($promo['used_at'])){$allowed=$usedRequest!==''&&hash_equals($usedRequest,$requestId);break;}
            if($reservedRequest!==''&&!hash_equals($reservedRequest,$requestId)){$allowed=false;break;}
            $promo['reserved_request_id']=$requestId;$promo['reserved_order_id']=$orderId;$promo['reserved_at']=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format(DATE_ATOM);$changed=true;
        }elseif($action==='use'){
            if($reservedRequest===''||!hash_equals($reservedRequest,$requestId)||!hash_equals((string)($promo['reserved_order_id']??''),$orderId)){$allowed=false;break;}
            $promo['used_at']=(new DateTimeImmutable('now',new DateTimeZone('Europe/Kyiv')))->format(DATE_ATOM);$promo['used_request_id']=$requestId;$promo['used_order_number']=$orderNumber;$promo['reserved_request_id']=null;$promo['reserved_order_id']=null;$promo['reserved_at']=null;$changed=true;
        }elseif($action==='release'){
            if($reservedRequest!==''&&hash_equals($reservedRequest,$requestId)&&hash_equals((string)($promo['reserved_order_id']??''),$orderId)){$promo['reserved_request_id']=null;$promo['reserved_order_id']=null;$promo['reserved_at']=null;$changed=true;}
        }
        break;
    }
    unset($promo);
    if($databaseValid&&!$found)$allowed=true;
    if($allowed&&$changed)$allowed=write_promocode_database_locked($path,$database);
    flock($lock,LOCK_UN);fclose($lock);return$allowed;
}

function reserve_single_use_promo(string $code,string $requestId,string $orderId): bool{return mutate_single_use_promo($code,$requestId,$orderId,'','reserve');}
function use_single_use_promo(string $code,string $requestId,string $orderId,string $orderNumber): bool{return mutate_single_use_promo($code,$requestId,$orderId,$orderNumber,'use');}
function release_single_use_promo(string $code,string $requestId,string $orderId): bool{return mutate_single_use_promo($code,$requestId,$orderId,'','release');}

function normalized_site_url(array $site): ?string
{
    $url = rtrim(trim((string)($site['site_url'] ?? '')), '/');
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return null;
    return $url;
}

function normalized_phone(mixed $value): ?string
{
    $phone = trim((string)$value);
    $digits = preg_replace('/\D+/', '', $phone);
    if ($phone === '' || strlen($digits) < 7 || strlen($digits) > 15) return null;
    return preg_replace('/[^0-9+()\- ]/', '', $phone);
}

function phone_href(mixed $value): ?string
{
    $phone = normalized_phone($value);
    if ($phone === null) return null;
    $digits = preg_replace('/\D+/', '', $phone);
    return 'tel:' . (str_starts_with($phone, '+') ? '+' : '') . $digits;
}

function normalized_telegram_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    $host = mb_strtolower((string)($parts['host'] ?? ''));
    if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, ['t.me','www.t.me','telegram.me','www.telegram.me'], true) || trim((string)($parts['path'] ?? ''), '/') === '') return null;
    return $url;
}

function normalized_instagram_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    $host = mb_strtolower((string)($parts['host'] ?? ''));
    if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, ['instagram.com','www.instagram.com'], true)) return null;
    $path = trim((string)($parts['path'] ?? ''), '/');
    if ($path === '' || str_contains($path, '/')) return null;
    return 'https://www.instagram.com/' . $path . '/';
}

function build_sitemap(array $site): bool
{
    $base = normalized_site_url($site);
    if ($base === null) return false;
    $paths = ['/', '/reservation.html', '/delivery.html', '/about.html', '/contacts.html', '/privacy.html', '/qr-menu/'];
    $items = '';
    foreach ($paths as $path) $items .= '  <url><loc>' . htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$items}</urlset>\n";
    $target = APP_ROOT . '/public/sitemap.xml';
    $tmp = tempnam(dirname($target), '.sitemap-');
    if ($tmp === false || file_put_contents($tmp, $xml, LOCK_EX) === false || !rename($tmp, $target)) {
        @unlink((string)$tmp); throw new RuntimeException('Не вдалося сформувати sitemap.xml');
    }
    chmod($target, 0644);
    $robots = "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\n\nSitemap: {$base}/sitemap.xml\n";
    $robotsTarget = APP_ROOT . '/public/robots.txt';
    $robotsTmp = tempnam(dirname($robotsTarget), '.robots-');
    if ($robotsTmp === false || file_put_contents($robotsTmp, $robots, LOCK_EX) === false || !rename($robotsTmp, $robotsTarget)) {
        @unlink((string)$robotsTmp); throw new RuntimeException('Не вдалося оновити robots.txt');
    }
    chmod($robotsTarget, 0644);
    return true;
}

function money(?int $minor): string
{
    if ($minor === null) return 'Ціну уточнюємо';
    return number_format($minor / 100, $minor % 100 === 0 ? 0 : 2, ',', ' ') . ' ₴';
}

function active_product_price(array $product, ?DateTimeImmutable $now = null): ?int
{
    $regular = $product['price_minor'] ?? null;
    if (!is_int($regular) || $regular < 0) return null;
    $promo = $product['promo_price_minor'] ?? null;
    if (!is_int($promo) || $promo < 0 || $promo >= $regular) return $regular;
    $now ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv'));
    $today = $now->format('Y-m-d');
    $start = (string)($product['promo_start'] ?? ''); $end = (string)($product['promo_end'] ?? '');
    if ($start !== '' && $today < $start) return $regular;
    if ($end !== '' && $today > $end) return $regular;
    return $promo;
}

function product_options(array $product): array
{
    return array_values(array_filter($product['options'] ?? [], static fn(mixed $option): bool => is_array($option) && !empty($option['id']) && !empty($option['name']) && is_int($option['price_minor'] ?? null) && $option['price_minor'] >= 0));
}

function date_rule_active(array $rule, ?DateTimeImmutable $now = null): bool
{
    if (($rule['active'] ?? false) !== true) return false;
    $today = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv')))->format('Y-m-d');
    $start = (string)($rule['start'] ?? ''); $end = (string)($rule['end'] ?? '');
    return !($start !== '' && $today < $start) && !($end !== '' && $today > $end);
}

function promotion_period_label(array $rule): string
{
    $end = (string)($rule['end'] ?? '');
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) ? DateTimeImmutable::createFromFormat('!Y-m-d', $end, new DateTimeZone('Europe/Kyiv')) : false;
    return $date ? 'До ' . $date->format('d.m.Y') : 'Пропозиція';
}

function delivery_configured(array $site): bool
{
    return is_int($site['delivery']['minimum_minor']??null)&&is_int($site['delivery']['fee_minor']??null);
}

function calculate_order_quote(array $items, array $catalogData, array $site, string $fulfillment, string $promoCode = '', string $promoRequestId = ''): array
{
    if (!in_array($fulfillment, ['pickup','dinein','delivery'], true)) throw new InvalidArgumentException('Оберіть спосіб отримання.');
    if (count($items) < 1 || count($items) > 50) throw new InvalidArgumentException('Кошик порожній або містить забагато позицій.');
    $products=[]; foreach($catalogData['products']??[] as $product) if(($product['available']??false)===true)$products[$product['id']]=$product;
    $verified=[]; $subtotal=0; $discountable=0; $totalWeight=0; $allWeightsKnown=true;
    foreach($items as $row){
        if(!is_array($row))throw new InvalidArgumentException('Некоректний рядок кошика.');
        $id=(string)($row['id']??'');$quantity=filter_var($row['quantity']??null,FILTER_VALIDATE_INT);
        if(!$quantity||$quantity<1||$quantity>30||!isset($products[$id]))throw new InvalidArgumentException('Кошик містить недоступну позицію. Оновіть сторінку.');
        $product=$products[$id];$basePrice=active_product_price($product);
        if(!is_int($basePrice))throw new InvalidArgumentException('Ціна однієї з позицій ще не налаштована.');
        $requested=$row['options']??[];if(!is_array($requested)||count($requested)>20)throw new InvalidArgumentException('Некоректні додаткові опції.');
        foreach($requested as $optionId)if(!is_string($optionId))throw new InvalidArgumentException('Некоректні додаткові опції.');
        if(count($requested)!==count(array_unique($requested)))throw new InvalidArgumentException('Некоректні додаткові опції.');
        $available=[];foreach(product_options($product)as $option)$available[$option['id']]=$option;
        $optionMode=($product['option_mode']??'multi')==='single'?'single':'multi';$optionsRequired=($product['options_required']??false)===true;
        if($optionMode==='single'&&count($requested)>1)throw new InvalidArgumentException('Для позиції «'.$product['name'].'» можна обрати лише одну опцію.');
        if($optionsRequired&&count($requested)<1)throw new InvalidArgumentException('Оберіть опцію для позиції «'.$product['name'].'».');
        $selected=[];$optionTotal=0;foreach($requested as $optionId){if(!isset($available[$optionId]))throw new InvalidArgumentException('Одна з додаткових опцій недоступна. Оновіть сторінку.');$selected[]=$available[$optionId];$optionTotal+=$available[$optionId]['price_minor'];}
        $unit=$basePrice+$optionTotal;$line=$unit*$quantity;$subtotal+=$line;
        $onPromotion=is_int($product['price_minor']??null)&&$basePrice<($product['price_minor']??0);
        if(!$onPromotion||($site['promocodes_stack_with_promos']??false)===true)$discountable+=$line;
        if(is_int($product['weight_g']??null))$totalWeight+=$product['weight_g']*$quantity;else$allWeightsKnown=false;
        $verified[]=['id'=>$id,'name'=>$product['name'],'ingredients'=>$product['ingredients']??null,'quantity'=>$quantity,'unit_minor'=>$unit,'line_minor'=>$line,'weight_g'=>$product['weight_g']??null,'options'=>$selected,'promotion'=>$onPromotion];
    }
    if ($fulfillment === 'delivery' && !delivery_configured($site)) throw new InvalidArgumentException('Доставка ще не налаштована. Оберіть самовивіз.');
    $discount=0;$appliedPromo=null;$promoCode=mb_strtoupper(trim($promoCode));
    if($promoCode!==''){
        foreach(promocode_rules($site)as$promo){if(mb_strtoupper((string)($promo['code']??''))!==$promoCode)continue;if(promocode_is_spent($promo,$promoRequestId))throw new InvalidArgumentException('Цей промокод уже використано.');if(!date_rule_active($promo))throw new InvalidArgumentException('Промокод неактивний або строк його дії завершився.');if($subtotal<(int)($promo['minimum_minor']??0))throw new InvalidArgumentException('Сума замовлення недостатня для цього промокоду.');$type=$promo['type']??'';$value=(int)($promo['value']??0);if($type==='percent'&&$value>0&&$value<=100)$discount=intdiv($discountable*$value+50,100);elseif($type==='fixed'&&$value>0)$discount=min($discountable,$value);else throw new InvalidArgumentException('Промокод налаштований некоректно.');$appliedPromo=$promoCode;break;}
        if($appliedPromo===null)throw new InvalidArgumentException('Промокод не знайдено.');
    }
    $afterDiscount=max(0,$subtotal-$discount);$gift=null;$giftRule=$site['gift']??[];$basis=($giftRule['threshold_basis']??'after_discount')==='before_discount'?$subtotal:$afterDiscount;
    if(date_rule_active($giftRule)&&is_int($giftRule['threshold_minor']??null)&&$basis>=$giftRule['threshold_minor']){$giftProduct=$products[$giftRule['product_id']??'']??null;if($giftProduct){$gift=['id'=>$giftProduct['id'],'name'=>$giftProduct['name'],'quantity'=>1,'line_minor'=>0,'weight_g'=>$giftProduct['weight_g']??null];if(is_int($giftProduct['weight_g']??null))$totalWeight+=$giftProduct['weight_g'];else$allWeightsKnown=false;}}
    $minimum=$site['delivery']['minimum_minor']??null;if($fulfillment==='delivery'&&is_int($minimum)&&$afterDiscount<$minimum)throw new InvalidArgumentException('Мінімальна сума замовлення для доставки - '.money($minimum).'.');
    $deliveryFee=0;if($fulfillment==='delivery'&&is_int($site['delivery']['fee_minor']??null)){$freeFrom=$site['delivery']['free_from_minor']??null;if(!is_int($freeFrom)||$afterDiscount<$freeFrom)$deliveryFee=$site['delivery']['fee_minor'];}
    return ['items'=>$verified,'subtotal_minor'=>$subtotal,'discount_minor'=>$discount,'promo_code'=>$appliedPromo,'gift'=>$gift,'delivery_minor'=>$deliveryFee,'total_minor'=>$afterDiscount+$deliveryFee,'weight_g'=>$allWeightsKnown?$totalWeight:null];
}

function active_banners(array $site, array $catalogData): array
{
    $products=[];foreach($catalogData['products']??[]as$product)$products[$product['id']]=$product;$result=[];
    foreach(array_slice($site['banners']??[],0,2)as$banner){if(!date_rule_active($banner))continue;$product=$products[$banner['product_id']??'']??null;if(!$product||($product['available']??false)!==true)continue;$banner['image']=$product['image']??'';$result[]=$banner;}return$result;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function product_image(array $product): string
{
    return '/assets/img/' . rawurlencode((string) ($product['image'] ?? ''));
}

function store_product_image(array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($upload['tmp_name'], $upload['size'])) {
        throw new RuntimeException('Оберіть зображення товару.');
    }
    if (!is_uploaded_file((string)$upload['tmp_name']) || (int)$upload['size'] < 1 || (int)$upload['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Файл зображення некоректний або перевищує 8 МБ.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file((string)$upload['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('Дозволені лише JPEG, PNG або WebP.');
    }
    $info = @getimagesize((string)$upload['tmp_name']);
    if (!$info || ($info[0] * $info[1]) > 40_000_000 || $info[0] < 100 || $info[1] < 100) {
        throw new RuntimeException('Некоректні розміри зображення.');
    }
    $raw = file_get_contents((string)$upload['tmp_name']);
    $source = $raw !== false ? @imagecreatefromstring($raw) : false;
    if (!$source) throw new RuntimeException('Не вдалося безпечно прочитати зображення.');
    $sourceWidth = imagesx($source); $sourceHeight = imagesy($source);
    $scale = min(1, 1600 / max($sourceWidth, $sourceHeight));
    $width = max(1, (int)round($sourceWidth * $scale)); $height = max(1, (int)round($sourceHeight * $scale));
    $canvas = imagecreatetruecolor($width, $height);
    imagealphablending($canvas, false); imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127); imagefill($canvas, 0, 0, $transparent);
    if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
        imagedestroy($source); imagedestroy($canvas); throw new RuntimeException('Не вдалося обробити зображення.');
    }
    $filename = 'product-' . bin2hex(random_bytes(12)) . '.webp';
    $target = APP_ROOT . '/public/assets/img/' . $filename;
    if (!imagewebp($canvas, $target, 82)) {
        imagedestroy($source); imagedestroy($canvas); throw new RuntimeException('Сервер не зміг створити WebP.');
    }
    imagedestroy($source); imagedestroy($canvas); chmod($target, 0644);
    return $filename;
}

function is_open_now(array $settings): ?bool
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Kyiv'));
    $hours = hours_for_date($settings, $now);
    if (!is_array($hours) || empty($hours['open']) || empty($hours['close'])) {
        return null;
    }
    $time = $now->format('H:i');
    return $time >= $hours['open'] && $time < $hours['close'];
}

function hours_for_date(array $settings, DateTimeImmutable $date): ?array
{
    $key = $date->format('Y-m-d');
    if (array_key_exists($key, $settings['exceptions'] ?? [])) {
        $exception = $settings['exceptions'][$key];
        return is_array($exception) ? $exception : null;
    }
    $day = strtolower($date->format('D'));
    $hours = $settings['hours'][$day] ?? null;
    return is_array($hours) ? $hours : null;
}

function available_slots(array $settings, DateTimeImmutable $date): array
{
    if (($settings['orders']['manual_enabled'] ?? false) !== true) return [];
    $timezone = new DateTimeZone('Europe/Kyiv');
    $today = new DateTimeImmutable('today', $timezone);
    $maxDate = $today->modify('+' . max(0, (int) ($settings['orders']['days_ahead'] ?? 7)) . ' days');
    if ($date < $today || $date > $maxDate) return [];
    $hours = order_hours_for_date($settings, $date);
    if (!is_array($hours) || !preg_match('/^\d{2}:\d{2}$/', (string)($hours['open'] ?? '')) || !preg_match('/^\d{2}:\d{2}$/', (string)($hours['close'] ?? ''))) return [];
    $step = max(10, min(120, (int) ($settings['orders']['slot_minutes'] ?? 30)));
    $cursor = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $hours['open'], $timezone);
    $close = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date->format('Y-m-d') . ' ' . $hours['close'], $timezone);
    if (!$cursor || !$close || $close <= $cursor) return [];
    $now = new DateTimeImmutable('now', $timezone);
    $leadMinutes = max(0, (int) ($settings['orders']['lead_minutes'] ?? 40));
    $earliest = $now->modify('+' . $leadMinutes . ' minutes');
    $slots = [];
    while ($cursor < $close) {
        if ($cursor >= $earliest) $slots[] = $cursor->format('H:i');
        $cursor = $cursor->modify('+' . $step . ' minutes');
    }
    return $slots;
}

function order_hours_for_date(array $settings, DateTimeImmutable $date): ?array
{
    if (hours_for_date($settings, $date) === null) return null;
    $hours = $settings['order_hours'] ?? null;
    return is_array($hours) ? $hours : hours_for_date($settings, $date);
}

function slot_label(array $settings, string $time): string
{
    if(!preg_match('/^\d{2}:\d{2}$/',$time))return $time;
    $start=DateTimeImmutable::createFromFormat('!H:i',$time,new DateTimeZone('Europe/Kyiv'));if(!$start)return$time;
    $step=max(10,min(120,(int)($settings['orders']['slot_minutes']??30)));
    return $time.'–'.$start->modify('+'.$step.' minutes')->format('H:i');
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('sushi_relax_admin');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => request_is_https(),
        'samesite' => 'Strict',
        'path' => '/admin/',
    ]);
    session_start();
}

function request_is_https(): bool
{
    if(!empty($_SERVER['HTTPS'])&&mb_strtolower((string)$_SERVER['HTTPS'])!=='off')return true;
    $forwarded=explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''));
    return mb_strtolower(trim($forwarded[0]??''))==='https';
}

function csrf_token(): string
{
    start_secure_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}

function verify_csrf(): void
{
    start_secure_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Сесію форми завершено. Оновіть сторінку.');
    }
}

function send_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function security_headers(): void
{
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function build_static_homepage(): void
{
    ob_start();
    require APP_ROOT . '/app/home-template.php';
    $html = ob_get_clean();
    if (!is_string($html) || !str_starts_with(ltrim($html), '<!doctype html>')) {
        throw new RuntimeException('Не вдалося сформувати index.html');
    }
    // Головна має працювати і через вебсервер, і при відкритті index.html напряму.
    $html = str_replace(
        ['href="/assets/', 'src="/assets/'],
        ['href="assets/', 'src="assets/'],
        $html
    );
    $target = APP_ROOT . '/public/index.html';
    $tmp = tempnam(dirname($target), '.index-');
    if ($tmp === false || file_put_contents($tmp, $html, LOCK_EX) === false || !rename($tmp, $target)) {
        @unlink((string) $tmp);
        throw new RuntimeException('Не вдалося записати index.html');
    }
    chmod($target, 0644);
    require_once APP_ROOT . '/app/static-pages.php';
    build_static_content_pages();
}

security_headers();
