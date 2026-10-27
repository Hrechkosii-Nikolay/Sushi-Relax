<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
start_secure_session();

function admin_text(mixed $value, int $length): string { return mb_substr(trim((string)$value), 0, $length); }
function admin_int(mixed $value, ?int $min = null, ?int $max = null): ?int {
    if ($value === '' || $value === null) return null;
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || ($min !== null && $number < $min) || ($max !== null && $number > $max)) return null;
    return $number;
}
function admin_money(mixed $value): ?int {
    $value = trim(str_replace(',', '.', (string)$value));
    if ($value === '' || !preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $match)) return null;
    $minor = ((int)$match[1] * 100) + (int)str_pad($match[2] ?? '', 2, '0');
    return $minor <= 99999999999 ? $minor : null;
}
function money_input(mixed $minor): string { return is_int($minor) ? number_format($minor / 100, 2, '.', '') : ''; }
function admin_id(string $prefix): string { return $prefix . '-' . bin2hex(random_bytes(5)); }
function admin_nested_upload(string $field, string $id): array {
    $files = $_FILES[$field] ?? [];
    return ['name'=>$files['name'][$id]??'','type'=>$files['type'][$id]??'','tmp_name'=>$files['tmp_name'][$id]??'','error'=>$files['error'][$id]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$id]??0];
}
function admin_structured_options(mixed $rows): array {
    if(!is_array($rows))return[];$result=[];$used=[];
    foreach(array_slice($rows,0,50)as$row){if(!is_array($row))continue;$name=admin_text($row['name']??'',80);$price=admin_money($row['price']??'');if($name===''||$price===null)continue;$id=(string)($row['id']??'');if(!preg_match('/^option-[a-f0-9]{10}$/',$id)||isset($used[$id]))$id=admin_id('option');$used[$id]=true;$result[]=['id'=>$id,'name'=>$name,'price_minor'=>$price];}
    return$result;
}
function admin_structured_promocodes(mixed $rows): array {
    if(!is_array($rows))return[];$result=[];$seen=[];
    foreach(array_slice($rows,0,50)as$row){if(!is_array($row))continue;$code=mb_strtoupper(preg_replace('/[^A-ZА-ЯІЇЄ0-9_-]/ui','',admin_text($row['code']??'',40)));if($code===''||isset($seen[$code]))continue;$type=($row['type']??'percent')==='fixed'?'fixed':'percent';$value=$type==='fixed'?admin_money($row['value']??''):admin_int($row['value']??'',1,100);if($value===null)continue;$start=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['start']??''))?$row['start']:null;$end=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['end']??''))?$row['end']:null;if($start&&$end&&$start>$end)[$start,$end]=[$end,$start];$seen[$code]=true;$result[]=['code'=>$code,'type'=>$type,'value'=>$value,'minimum_minor'=>admin_money($row['minimum']??'')??0,'start'=>$start,'end'=>$end,'active'=>isset($row['active'])];}
    return$result;
}
function admin_structured_banners(mixed $rows,array $productIds): array {
    if(!is_array($rows))return[];$result=[];
    foreach($rows as$row){if(count($result)>=2)break;if(!is_array($row))continue;$title=admin_text($row['title']??'',100);$productId=admin_text($row['product_id']??'',100);if($title===''||!in_array($productId,$productIds,true))continue;$link=admin_text($row['link']??'#menu',200);if(!str_starts_with($link,'/')&&!str_starts_with($link,'#'))$link='#menu';$start=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['start']??''))?$row['start']:null;$end=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['end']??''))?$row['end']:null;if($start&&$end&&$start>$end)[$start,$end]=[$end,$start];$id=preg_match('/^banner-[a-f0-9]{10}$/',(string)($row['id']??''))?$row['id']:admin_id('banner');$result[]=['id'=>$id,'title'=>$title,'product_id'=>$productId,'link'=>$link?:'#menu','start'=>$start,'end'=>$end,'active'=>isset($row['active'])];}
    return$result;
}
function admin_system_checks(string $hash,array $site): array {
    $extensions=['curl','fileinfo','gd','json','mbstring','openssl'];$missing=array_values(array_filter($extensions,static fn(string $extension):bool=>!extension_loaded($extension)));
    $runtime=VAR_ROOT.'/runtime';$logs=VAR_ROOT.'/logs';$backups=backup_root();$documentRoot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$publicRoot=realpath(APP_ROOT.'/public');
    $displayErrors=filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN);$hashValid=$hash!==''&&(password_get_info($hash)['algoName']??'unknown')!=='unknown';$telegramReady=app_secret('SUSHI_TELEGRAM_BOT_TOKEN','telegram_bot_token')!==''&&app_secret('SUSHI_TELEGRAM_CHAT_ID','telegram_chat_id')!=='';
    return[
      ['status'=>version_compare(PHP_VERSION,'8.1.0','>=')?'ok':'error','title'=>'PHP 8.1+','detail'=>'Встановлено '.PHP_VERSION],
      ['status'=>$missing?'error':'ok','title'=>'Розширення PHP','detail'=>$missing?'Відсутні: '.implode(', ',$missing):'Усі необхідні розширення доступні'],
      ['status'=>is_readable(DATA_ROOT)&&is_writable(DATA_ROOT)?'ok':'error','title'=>'Каталог data','detail'=>is_readable(DATA_ROOT)&&is_writable(DATA_ROOT)?'Читання і запис доступні':'Потрібні права читання та запису'],
      ['status'=>(is_dir($runtime)?is_writable($runtime):is_writable(VAR_ROOT))?'ok':'error','title'=>'Технічні файли','detail'=>'Запис у var/runtime'],
      ['status'=>(is_dir($logs)?is_writable($logs):is_writable(VAR_ROOT))?'ok':'error','title'=>'Технічний журнал','detail'=>'Запис у приватний var/logs'],
      ['status'=>(is_dir($backups)?is_writable($backups):is_writable(dirname($backups)))?'ok':'error','title'=>'Резервні копії','detail'=>'Запис у приватний каталог копій'],
      ['status'=>$documentRoot!==false&&$publicRoot!==false&&$documentRoot===$publicRoot?'ok':'error','title'=>'Публічний корінь','detail'=>$documentRoot===$publicRoot?'Домен спрямовано в public/':'DocumentRoot має вказувати саме на public/'],
      ['status'=>request_is_https()?'ok':'error','title'=>'HTTPS','detail'=>request_is_https()?'Захищене з’єднання визначено':'HTTPS не визначено'],
      ['status'=>$displayErrors?'error':'ok','title'=>'PHP-помилки','detail'=>$displayErrors?'display_errors потрібно вимкнути':'Помилки не показуються відвідувачам'],
      ['status'=>$hashValid?'ok':'error','title'=>'Пароль адміністратора','detail'=>$hashValid?'Хеш розпізнано':'SUSHI_ADMIN_PASSWORD_HASH не задано або має неправильний формат'],
      ['status'=>$telegramReady?'ok':'error','title'=>'Telegram','detail'=>$telegramReady?'Обидва секрети задані':'Потрібні Bot Token і Chat ID'],
      ['status'=>normalized_site_url($site)!==null?'ok':'manual','title'=>'Домен і SEO','detail'=>normalized_site_url($site)!==null?'HTTPS-домен задано':'Вкажіть фінальний домен у налаштуваннях'],
      ['status'=>'manual','title'=>'Cron','detail'=>'Перевірте щоденний запуск scripts/backup.php у панелі хостингу'],
    ];
}

$hash = app_secret('SUSHI_ADMIN_PASSWORD_HASH','admin_password_hash');
$error = ''; $flash = '';
if (isset($_GET['logout'])) { session_destroy(); header('Location: /admin/'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    verify_csrf();
    $now=time();$windowStarted=(int)($_SESSION['login_window_started']??0);
    if($windowStarted===0||$windowStarted+900<=$now){$_SESSION['login_window_started']=$now;$_SESSION['login_attempts']=0;}
    $attempts = (int)($_SESSION['login_attempts'] ?? 0);
    $rateAllowed = acquire_rate_limit('admin-login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 8, 900);
    if (!$rateAllowed || $attempts >= 8) $error = 'Забагато спроб. Зачекайте 15 хвилин і спробуйте знову.';
    elseif ($hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash)) { session_regenerate_id(true); $_SESSION['admin']=true;unset($_SESSION['login_attempts'],$_SESSION['login_window_started']);header('Location: /admin/'); exit; }
    else { $_SESSION['login_attempts']=$attempts+1; $error = $hash === '' ? 'Пароль адміністратора ще не налаштовано.' : 'Невірний пароль.'; }
}
$logged = ($_SESSION['admin'] ?? false) === true;

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    verify_csrf(); try{$name=create_data_backup();$flash='Резервну копію створено: '.$name;}catch(Throwable $exception){$error=$exception->getMessage();}
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    verify_csrf();$confirmation=trim((string)($_POST['restore_confirmation']??''));
    if($confirmation!=='ВІДНОВИТИ')$error='Для відновлення введіть слово ВІДНОВИТИ.';
    else{try{restore_data_backup(admin_text($_POST['restore_backup'],120));$flash='Дані відновлено. Попередній стан автоматично збережено окремою копією.';}catch(Throwable $exception){$error=$exception->getMessage();}}
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    verify_csrf(); $data = read_json('catalog.json'); $name = admin_text($_POST['category_name'] ?? '', 80);
    if ($name === '') $error = 'Вкажіть назву категорії.';
    else {
        $data['categories'][] = ['id'=>admin_id('category'),'name'=>$name,'order'=>count($data['categories']) * 10 + 10];
        try { write_json_atomic('catalog.json',$data); build_static_homepage(); $flash='Категорію додано.'; } catch(Throwable) { $error='Не вдалося додати категорію.'; }
    }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_categories'])) {
    verify_csrf(); $data = read_json('catalog.json');
    foreach ($data['categories'] as &$category) {
        $row = $_POST['category'][$category['id']] ?? [];
        $category['name'] = admin_text($row['name'] ?? $category['name'], 80) ?: $category['name'];
        $category['order'] = admin_int($row['order'] ?? '', 0, 100000) ?? 0;
    } unset($category);
    try { write_json_atomic('catalog.json',$data); build_static_homepage(); $flash='Категорії оновлено.'; } catch(Throwable) { $error='Не вдалося зберегти категорії.'; }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    verify_csrf(); $data = read_json('catalog.json'); $id = admin_text($_POST['delete_category'], 80);
    $used = array_filter(array_merge($data['products'] ?? [],$data['archived_products'] ?? []), static fn(array $product): bool => ($product['category'] ?? '') === $id);
    if ($used) $error = 'Категорію не видалено: спочатку перенесіть усі її товари.';
    else {
        $data['categories'] = array_values(array_filter($data['categories'], static fn(array $category): bool => $category['id'] !== $id));
        try { write_json_atomic('catalog.json',$data); build_static_homepage(); $flash='Порожню категорію видалено.'; } catch(Throwable) { $error='Не вдалося видалити категорію.'; }
    }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    verify_csrf(); $data = read_json('catalog.json'); $name = admin_text($_POST['new_product']['name'] ?? '', 120); $category = admin_text($_POST['new_product']['category'] ?? '', 80);
    if ($name === '' || !in_array($category, array_column($data['categories'],'id'), true)) $error='Заповніть назву та категорію нового товару.';
    else {
        try {
            $image = store_product_image($_FILES['product_image'] ?? []);
            $data['products'][] = ['id'=>admin_id('product'),'category'=>$category,'name'=>$name,'image'=>$image,'price_minor'=>admin_money($_POST['new_product']['price']??''),'weight_g'=>null,'prep_minutes'=>null,'description'=>null,'ingredients'=>null,'badge'=>null,'available'=>false,'order'=>1000];
            write_json_atomic('catalog.json',$data); build_static_homepage(); $flash='Товар додано прихованим. Заповніть його дані та увімкніть показ.';
        } catch(Throwable $exception) { $error=$exception->getMessage(); }
    }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_product'])) {
    verify_csrf(); $data=read_json('catalog.json'); $id=admin_text($_POST['archive_product'],80); $remaining=[]; $archived=$data['archived_products']??[]; $found=false;
    foreach($data['products'] as $product){ if($product['id']===$id){$product['available']=false;$product['archived_at']=(new DateTimeImmutable())->format(DATE_ATOM);$archived[]=$product;$found=true;}else{$remaining[]=$product;} }
    if($found){$data['products']=$remaining;$data['archived_products']=$archived;try{write_json_atomic('catalog.json',$data);build_static_homepage();$flash='Товар переміщено в архів.';}catch(Throwable){$error='Не вдалося архівувати товар.';}} else $error='Товар не знайдено.';
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_product'])) {
    verify_csrf(); $data=read_json('catalog.json'); $id=admin_text($_POST['restore_product'],80); $remaining=[]; $found=false;
    foreach(($data['archived_products']??[]) as $product){if($product['id']===$id){unset($product['archived_at']);$product['available']=false;$data['products'][]=$product;$found=true;}else{$remaining[]=$product;}}
    if($found){$data['archived_products']=$remaining;try{write_json_atomic('catalog.json',$data);build_static_homepage();$flash='Товар відновлено прихованим.';}catch(Throwable){$error='Не вдалося відновити товар.';}} else $error='Архівний товар не знайдено.';
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_archived_product'])) {
    verify_csrf();
    $id=admin_text($_POST['delete_archived_product'],80);
    $confirmation=trim((string)($_POST['delete_product_confirmation']??''));
    if($confirmation!=='ВИДАЛИТИ')$error='Для остаточного видалення введіть слово ВИДАЛИТИ.';
    else{
        $data=read_json('catalog.json');$site=read_json('settings.json');$remaining=[];$deleted=null;
        foreach(($data['archived_products']??[])as$product){if(($product['id']??'')===$id)$deleted=$product;else$remaining[]=$product;}
        if(!$deleted)$error='Архівний товар не знайдено.';
        else{
            try{
                create_data_backup();
                $data['archived_products']=$remaining;
                foreach(($data['products']??[])as&$product)$product['recommendations']=array_values(array_filter($product['recommendations']??[],static fn(mixed $value):bool=>$value!==$id));unset($product);
                $site['banners']=array_values(array_filter($site['banners']??[],static fn(array $banner):bool=>($banner['product_id']??'')!==$id));
                if(($site['gift']['product_id']??'')===$id)$site['gift']=['active'=>false,'product_id'=>null,'threshold_minor'=>null,'threshold_basis'=>'after_discount','start'=>null,'end'=>null];
                write_json_atomic('catalog.json',$data);write_json_atomic('settings.json',$site);build_static_homepage();
                $image=basename((string)($deleted['image']??''));$stillUsed=false;
                foreach(array_merge($data['products']??[],$data['archived_products']??[])as$product)if(($product['image']??'')===$image)$stillUsed=true;
                if(!$stillUsed&&preg_match('/^product-[a-f0-9]{24}\.webp$/',$image))@unlink(APP_ROOT.'/public/assets/img/'.$image);
                technical_log('admin.product_deleted',['product_id'=>$id]);$flash='Архівний товар остаточно видалено. Перед видаленням створено резервну копію.';
            }catch(Throwable $exception){technical_log('admin.product_delete_failed',['exception'=>get_class($exception)]);$error='Не вдалося остаточно видалити товар.';}
        }
    }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_catalog'])) {
    verify_csrf(); $data = read_json('catalog.json');
    $categoryIds = array_column($data['categories'], 'id');
    foreach ($data['products'] as &$product) {
        $id = $product['id']; if (!isset($_POST['product'][$id])) continue; $row = $_POST['product'][$id];
        $category = admin_text($row['category'] ?? '', 60);
        $badge = admin_text($row['badge'] ?? '', 30);
        $product['name'] = admin_text($row['name'] ?? '', 120);
        $product['category'] = in_array($category, $categoryIds, true) ? $category : $product['category'];
        $product['description'] = admin_text($row['description'] ?? '', 240) ?: null;
        $product['ingredients'] = admin_text($row['ingredients'] ?? '', 500) ?: null;
        $product['price_minor'] = admin_money($row['price'] ?? '');
        $product['promo_price_minor'] = admin_money($row['promo_price'] ?? '');
        $product['promo_start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['promo_start']??'')) ? $row['promo_start'] : null;
        $product['promo_end'] = preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['promo_end']??'')) ? $row['promo_end'] : null;
        $product['weight_g'] = admin_int($row['weight_g'] ?? '', 1, 10000);
        $product['prep_minutes'] = admin_int($row['prep_minutes'] ?? '', 1, 1440);
        $product['order'] = admin_int($row['order'] ?? '', 0, 100000) ?? 0;
        $product['badge'] = in_array($badge, ['Новинка', 'Хіт продажів', 'Хіт'], true) ? ($badge === 'Хіт' ? 'Хіт продажів' : $badge) : null;
        $product['options'] = admin_structured_options($row['options'] ?? []);
        $recommendationInput = is_array($row['recommendations'] ?? null) ? $row['recommendations'] : [];
        $recommendations = array_values(array_filter($recommendationInput, static fn(mixed $value): bool => is_string($value)));
        $product['recommendations'] = array_values(array_unique(array_diff(array_intersect($recommendations, array_column($data['products'],'id')), [$id])));
        $product['available'] = isset($row['available']);
        $upload = admin_nested_upload('product_image', $id);
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try { $product['image'] = store_product_image($upload); } catch(Throwable $exception) { $error = $exception->getMessage(); }
        }
    } unset($product);
    try { write_json_atomic('catalog.json', $data); build_static_homepage(); $flash='Каталог та index.html оновлено.'; } catch(Throwable) { $error='Не вдалося зберегти каталог.'; }
}

if ($logged && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    verify_csrf(); $site = read_json('settings.json'); $row = $_POST['settings'] ?? [];
    $siteUrl = rtrim(admin_text($row['site_url'] ?? '', 240), '/');
    $site['site_url'] = $siteUrl !== '' && normalized_site_url(['site_url'=>$siteUrl]) !== null ? $siteUrl : null;
    $site['address'] = admin_text($row['address'] ?? '', 180) ?: null;
    $site['city'] = admin_text($row['city'] ?? '', 80) ?: null;
    $site['phone'] = normalized_phone(admin_text($row['phone'] ?? '', 30));
    $site['instagram_url'] = normalized_instagram_url(admin_text($row['instagram_url'] ?? '', 200));
    $site['telegram_contact'] = normalized_telegram_url(admin_text($row['telegram_contact'] ?? '', 200));
    $site['about'] = admin_text($row['about'] ?? '', 2000);
    $site['privacy_text'] = admin_text($row['privacy_text'] ?? '', 5000);
    $site['orders']['manual_enabled'] = isset($row['manual_enabled']);
    $site['orders']['days_ahead'] = admin_int($row['days_ahead'] ?? '', 0, 30) ?? 7;
    $site['orders']['slot_minutes'] = admin_int($row['slot_minutes'] ?? '', 10, 120) ?? 30;
    $site['orders']['lead_minutes'] = admin_int($row['lead_minutes'] ?? '', 0, 360) ?? 60;
    $site['delivery']['minimum_minor'] = admin_money($row['delivery_minimum'] ?? '');
    $site['delivery']['fee_minor'] = admin_money($row['delivery_fee'] ?? '');
    $site['delivery']['free_from_minor'] = admin_money($row['delivery_free_from'] ?? '');
    $site['promocodes'] = admin_structured_promocodes($row['promocodes'] ?? []);
    $site['promocodes_stack_with_promos'] = isset($row['promocodes_stack_with_promos']);
    $catalogForSettings=read_json('catalog.json');$productIds=array_column($catalogForSettings['products']??[],'id');
    $site['banners'] = admin_structured_banners($row['banners'] ?? [],$productIds);
    $giftProduct=admin_text($row['gift_product_id']??'',100);
    $site['gift']=['active'=>isset($row['gift_active'])&&in_array($giftProduct,$productIds,true),'product_id'=>in_array($giftProduct,$productIds,true)?$giftProduct:null,'threshold_minor'=>admin_money($row['gift_threshold']??''),'threshold_basis'=>($row['gift_threshold_basis']??'after_discount')==='before_discount'?'before_discount':'after_discount','start'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['gift_start']??''))?$row['gift_start']:null,'end'=>preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($row['gift_end']??''))?$row['gift_end']:null];
    foreach (['mon','tue','wed','thu','fri','sat','sun'] as $day) {
        $enabled = isset($row['hours'][$day]['enabled']);
        $open = admin_text($row['hours'][$day]['open'] ?? '', 5); $close = admin_text($row['hours'][$day]['close'] ?? '', 5);
        $site['hours'][$day] = $enabled && preg_match('/^\d{2}:\d{2}$/', $open) && preg_match('/^\d{2}:\d{2}$/', $close) && $open < $close ? ['open'=>$open,'close'=>$close] : null;
    }
    $orderOpen = admin_text($row['order_hours']['open'] ?? '', 5);
    $orderClose = admin_text($row['order_hours']['close'] ?? '', 5);
    $site['order_hours'] = preg_match('/^\d{2}:\d{2}$/', $orderOpen) && preg_match('/^\d{2}:\d{2}$/', $orderClose) && $orderOpen < $orderClose ? ['open'=>$orderOpen,'close'=>$orderClose] : null;
    $exceptions = [];
    foreach (preg_split('/\R/u', (string)($row['exceptions'] ?? '')) ?: [] as $line) {
        $line = trim($line); if ($line === '') continue;
        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*=\s*(closed|вихідний)$/ui', $line, $match)) $exceptions[$match[1]] = null;
        elseif (preg_match('/^(\d{4}-\d{2}-\d{2})\s*=\s*(\d{2}:\d{2})-(\d{2}:\d{2})$/', $line, $match) && $match[2] < $match[3]) $exceptions[$match[1]] = ['open'=>$match[2],'close'=>$match[3]];
    }
    $site['exceptions'] = $exceptions;
    try { write_json_atomic('settings.json', $site); build_static_homepage(); $sitemapBuilt=build_sitemap($site); $flash='Налаштування та index.html оновлено.'.($sitemapBuilt?' Sitemap теж оновлено.':' Додайте HTTPS-адресу сайту для sitemap.'); } catch(Throwable) { $error='Не вдалося зберегти налаштування.'; }
}

$catalogData = $logged ? read_json('catalog.json') : [];
$siteData = $logged ? read_json('settings.json') : [];
$backups = $logged ? list_data_backups() : [];
$systemChecks = $logged ? admin_system_checks($hash,$siteData) : [];
$dayNames = ['mon'=>'Понеділок','tue'=>'Вівторок','wed'=>'Середа','thu'=>'Четвер','fri'=>'П’ятниця','sat'=>'Субота','sun'=>'Неділя'];
$exceptionsText = '';
if ($logged) foreach (($siteData['exceptions'] ?? []) as $date=>$hours) $exceptionsText .= $date . '=' . (is_array($hours) ? $hours['open'].'-'.$hours['close'] : 'closed') . "\n";
?>
<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Адмінпанель - Sushi Relax</title><link rel="icon" href="/assets/img/sushi-relax-logo.webp" type="image/webp"><link rel="stylesheet" href="/assets/css/styles.css?v=20261026-4"></head><body>
<main class="admin-shell"><a class="brand" href="/"><img class="brand-logo" src="/assets/img/sushi-relax-logo.webp" alt="" width="50" height="50"><span>Sushi <em>Relax</em></span></a>
<?php if (!$logged): ?>
  <section class="content-page admin-login"><p class="eyebrow">Захищений доступ</p><h1>Вхід</h1><?php if($error):?><p class="flash flash--error"><?=h($error)?></p><?php endif;?><form method="post" class="field admin-login-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label>Пароль<input class="admin-input" type="password" name="password" required autocomplete="current-password"></label><button class="button" name="login" value="1">Увійти</button></form></section>
<?php else: ?>
  <div class="admin-heading"><div><p class="eyebrow">Керування сайтом</p><h1 class="admin-title">Sushi Relax</h1></div><a class="text-link" href="?logout=1">Вийти</a></div>
  <nav class="admin-nav" aria-label="Розділи адмінпанелі"><a href="#system">Сервер</a><a href="#settings">Налаштування</a><a href="#schedule">Графік</a><a href="#marketing">Акції</a><a href="#backups">Копії</a><a href="#categories">Категорії</a><a href="#add-product">Новий товар</a><a href="#catalog">Каталог</a><a href="/" target="_blank" rel="noopener">Відкрити сайт ↗</a></nav>
  <?php if($flash):?><p class="flash"><?=h($flash)?></p><?php endif;?><?php if($error):?><p class="flash flash--error"><?=h($error)?></p><?php endif;?>

  <section class="admin-section" id="system"><div class="admin-section-head"><div><p class="eyebrow">Fornex / production</p><h2>Готовність сервера</h2></div><p class="system-score"><?=count(array_filter($systemChecks,static fn(array $check):bool=>$check['status']==='ok'))?> / <?=count($systemChecks)?> перевірок пройдено</p></div><p class="form-note">Панель не показує значення секретів і не може підтвердити cron без фактичного запуску на хостингу.</p><div class="system-checks"><?php foreach($systemChecks as $check):?><article class="system-check system-check--<?=h($check['status'])?>"><span class="system-check-mark" aria-hidden="true"><?=$check['status']==='ok'?'✓':($check['status']==='manual'?'!':'×')?></span><div><strong><?=h($check['title'])?></strong><small><?=h($check['detail'])?></small></div></article><?php endforeach;?></div></section>

  <form method="post" class="admin-section" id="settings"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="admin-section-head"><div><p class="eyebrow">Основне</p><h2>Налаштування</h2></div><button class="button" name="save_settings" value="1">Зберегти налаштування</button></div>
    <div class="admin-form-grid">
      <label class="field field--full"><span>HTTPS-адреса сайту для SEO</span><input type="url" name="settings[site_url]" value="<?=h($siteData['site_url']??'')?>" placeholder="https://example.com"></label>
      <label class="field"><span>Місто</span><input name="settings[city]" value="<?=h($siteData['city']??'')?>"></label>
      <label class="field"><span>Адреса</span><input name="settings[address]" value="<?=h($siteData['address']??'')?>"></label>
      <label class="field"><span>Телефон</span><input type="tel" name="settings[phone]" value="<?=h($siteData['phone']??'')?>" maxlength="30" placeholder="+380 …"></label>
      <label class="field"><span>Instagram закладу</span><input type="url" name="settings[instagram_url]" value="<?=h($siteData['instagram_url']??'')?>" placeholder="https://www.instagram.com/..."></label>
      <label class="field"><span>Telegram закладу</span><input type="url" name="settings[telegram_contact]" value="<?=h($siteData['telegram_contact']??'')?>" placeholder="https://t.me/..."><small>Лише публічне посилання t.me закладу, не токен бота.</small></label>
      <label class="field field--full"><span>Про заклад</span><textarea name="settings[about]"><?=h($siteData['about']??'')?></textarea></label>
      <label class="field field--full"><span>Текст політики</span><textarea name="settings[privacy_text]"><?=h($siteData['privacy_text']??'')?></textarea></label>
    </div>
    <div class="admin-subsection" id="schedule"><h3>Онлайн-замовлення та графік</h3><label class="switch-row"><input type="checkbox" name="settings[manual_enabled]" <?=($siteData['orders']['manual_enabled']??false)?'checked':''?>><span>Приймати онлайн-замовлення</span></label>
      <div class="admin-form-grid admin-form-grid--three"><label class="field"><span>Днів наперед</span><input type="number" min="0" max="30" name="settings[days_ahead]" value="<?=(int)($siteData['orders']['days_ahead']??7)?>"></label><label class="field"><span>Крок слотів, хв</span><input type="number" min="10" max="120" name="settings[slot_minutes]" value="<?=(int)($siteData['orders']['slot_minutes']??30)?>"></label><label class="field"><span>Час на приготування, хв</span><input type="number" min="0" max="360" name="settings[lead_minutes]" value="<?=(int)($siteData['orders']['lead_minutes']??60)?>"></label></div>
      <div class="hours-grid"><?php foreach($dayNames as $key=>$label): $hours=$siteData['hours'][$key]??null; ?><div class="hours-row"><label><input type="checkbox" name="settings[hours][<?=$key?>][enabled]" <?=is_array($hours)?'checked':''?>> <?=$label?></label><input type="time" name="settings[hours][<?=$key?>][open]" value="<?=h($hours['open']??'')?>"><span>-</span><input type="time" name="settings[hours][<?=$key?>][close]" value="<?=h($hours['close']??'')?>"></div><?php endforeach;?></div>
      <div class="admin-form-grid"><label class="field"><span>Замовлення приймаємо з</span><input type="time" name="settings[order_hours][open]" value="<?=h($siteData['order_hours']['open']??'')?>"></label><label class="field"><span>Замовлення приймаємо до</span><input type="time" name="settings[order_hours][close]" value="<?=h($siteData['order_hours']['close']??'')?>"></label></div>
      <label class="field"><span>Винятки - один на рядок: 2026-12-25=closed або 2026-12-31=10:00-18:00</span><textarea name="settings[exceptions]" placeholder="РРРР-ММ-ДД=closed"><?=h(trim($exceptionsText))?></textarea></label>
    </div>
    <div class="admin-subsection"><h3>Доставка</h3><p class="form-note">Порожнє поле означає «не налаштовано».</p><div class="admin-form-grid admin-form-grid--three"><label class="field"><span>Мінімальна сума, грн</span><input inputmode="decimal" name="settings[delivery_minimum]" value="<?=h(money_input($siteData['delivery']['minimum_minor']??null))?>"></label><label class="field"><span>Вартість, грн</span><input inputmode="decimal" name="settings[delivery_fee]" value="<?=h(money_input($siteData['delivery']['fee_minor']??null))?>"></label><label class="field"><span>Безкоштовно від, грн</span><input inputmode="decimal" name="settings[delivery_free_from]" value="<?=h(money_input($siteData['delivery']['free_from_minor']??null))?>"></label></div></div>
    <div class="admin-subsection" id="marketing"><div class="admin-section-head"><div><h3>Промокоди</h3><p class="form-note">Значення фіксованої знижки та мінімальна сума вказуються у гривнях.</p></div><button class="text-button" type="button" data-add-admin-row="promocodes">＋ Додати промокод</button></div>
      <div class="rule-list" data-admin-rows="promocodes"><?php $promoRows=$siteData['promocodes']??[];if(!$promoRows)$promoRows=[[]];foreach($promoRows as $promoIndex=>$promo):?><div class="rule-row" data-admin-row>
        <label class="field"><span>Код</span><input name="settings[promocodes][<?=h((string)$promoIndex)?>][code]" value="<?=h($promo['code']??'')?>" maxlength="40" placeholder="RELAX10"></label>
        <label class="field"><span>Тип</span><select name="settings[promocodes][<?=h((string)$promoIndex)?>][type]"><option value="percent" <?=($promo['type']??'percent')==='percent'?'selected':''?>>Відсоток</option><option value="fixed" <?=($promo['type']??'')==='fixed'?'selected':''?>>Фіксована сума</option></select></label>
        <label class="field"><span>Знижка</span><input inputmode="decimal" name="settings[promocodes][<?=h((string)$promoIndex)?>][value]" value="<?=h(isset($promo['value'])?(($promo['type']??'percent')==='fixed'?money_input($promo['value']):(string)$promo['value']):'')?>" placeholder="10"></label>
        <label class="field"><span>Мінімальна сума, грн</span><input inputmode="decimal" name="settings[promocodes][<?=h((string)$promoIndex)?>][minimum]" value="<?=h(money_input($promo['minimum_minor']??0))?>"></label>
        <label class="field"><span>Початок</span><input type="date" name="settings[promocodes][<?=h((string)$promoIndex)?>][start]" value="<?=h($promo['start']??'')?>"></label>
        <label class="field"><span>Завершення</span><input type="date" name="settings[promocodes][<?=h((string)$promoIndex)?>][end]" value="<?=h($promo['end']??'')?>"></label>
        <label class="switch-row"><input type="checkbox" name="settings[promocodes][<?=h((string)$promoIndex)?>][active]" <?=($promo['active']??false)?'checked':''?>><span>Активний</span></label><button class="text-button text-button--danger rule-remove" type="button" data-remove-admin-row>Видалити</button>
      </div><?php endforeach;?></div>
      <label class="switch-row"><input type="checkbox" name="settings[promocodes_stack_with_promos]" <?=($siteData['promocodes_stack_with_promos']??false)?'checked':''?>><span>Дозволити промокод на товари з акційною ціною</span></label>
    </div>
    <div class="admin-subsection"><h3>Подарунок</h3><div class="admin-form-grid"><label class="field"><span>Товар-подарунок</span><select name="settings[gift_product_id]"><option value="">Не вибрано</option><?php foreach($catalogData['products'] as $product):?><option value="<?=h($product['id'])?>" <?=($siteData['gift']['product_id']??'')===$product['id']?'selected':''?>><?=h($product['name'])?></option><?php endforeach;?></select></label><label class="field"><span>Поріг, грн</span><input inputmode="decimal" name="settings[gift_threshold]" value="<?=h(money_input($siteData['gift']['threshold_minor']??null))?>"></label><label class="field"><span>Поріг рахується</span><select name="settings[gift_threshold_basis]"><option value="after_discount" <?=($siteData['gift']['threshold_basis']??'after_discount')==='after_discount'?'selected':''?>>Після знижки</option><option value="before_discount" <?=($siteData['gift']['threshold_basis']??'')==='before_discount'?'selected':''?>>До знижки</option></select></label><div class="field"><span>Період</span><div class="date-pair"><input type="date" name="settings[gift_start]" value="<?=h($siteData['gift']['start']??'')?>"><input type="date" name="settings[gift_end]" value="<?=h($siteData['gift']['end']??'')?>"></div></div></div><label class="switch-row"><input type="checkbox" name="settings[gift_active]" <?=($siteData['gift']['active']??false)?'checked':''?>><span>Подарунок активний</span></label></div>
    <div class="admin-subsection"><div class="admin-section-head"><div><h3>Банери головної</h3><p class="form-note">До двох банерів. Фото автоматично береться з вибраного товару.</p></div><button class="text-button" type="button" data-add-admin-row="banners">＋ Додати банер</button></div>
      <div class="rule-list" data-admin-rows="banners" data-max-rows="2"><?php $bannerRows=$siteData['banners']??[];if(!$bannerRows)$bannerRows=[[]];foreach($bannerRows as $bannerIndex=>$banner):?><div class="rule-row rule-row--banner" data-admin-row>
        <input type="hidden" name="settings[banners][<?=h((string)$bannerIndex)?>][id]" value="<?=h($banner['id']??'')?>">
        <label class="field"><span>Заголовок</span><input name="settings[banners][<?=h((string)$bannerIndex)?>][title]" value="<?=h($banner['title']??'')?>" maxlength="100" placeholder="Спеціальна пропозиція"></label>
        <label class="field"><span>Товар і фото</span><select name="settings[banners][<?=h((string)$bannerIndex)?>][product_id]"><option value="">Оберіть товар</option><?php foreach($catalogData['products'] as $product):?><option value="<?=h($product['id'])?>" <?=($banner['product_id']??'')===$product['id']?'selected':''?>><?=h($product['name'])?></option><?php endforeach;?></select></label>
        <label class="field"><span>Посилання</span><input name="settings[banners][<?=h((string)$bannerIndex)?>][link]" value="<?=h($banner['link']??'#menu')?>" placeholder="#menu"></label>
        <label class="field"><span>Початок</span><input type="date" name="settings[banners][<?=h((string)$bannerIndex)?>][start]" value="<?=h($banner['start']??'')?>"></label>
        <label class="field"><span>Завершення</span><input type="date" name="settings[banners][<?=h((string)$bannerIndex)?>][end]" value="<?=h($banner['end']??'')?>"></label>
        <label class="switch-row"><input type="checkbox" name="settings[banners][<?=h((string)$bannerIndex)?>][active]" <?=($banner['active']??false)?'checked':''?>><span>Активний</span></label><button class="text-button text-button--danger rule-remove" type="button" data-remove-admin-row>Видалити</button>
      </div><?php endforeach;?></div>
    </div>
  </form>

  <template id="admin-promocodes-template"><div class="rule-row" data-admin-row>
    <label class="field"><span>Код</span><input data-field="code" maxlength="40" placeholder="RELAX10"></label><label class="field"><span>Тип</span><select data-field="type"><option value="percent">Відсоток</option><option value="fixed">Фіксована сума</option></select></label><label class="field"><span>Знижка</span><input data-field="value" inputmode="decimal" placeholder="10"></label><label class="field"><span>Мінімальна сума, грн</span><input data-field="minimum" inputmode="decimal" value="0.00"></label><label class="field"><span>Початок</span><input data-field="start" type="date"></label><label class="field"><span>Завершення</span><input data-field="end" type="date"></label><label class="switch-row"><input data-field="active" type="checkbox"><span>Активний</span></label><button class="text-button text-button--danger rule-remove" type="button" data-remove-admin-row>Видалити</button>
  </div></template>
  <template id="admin-banners-template"><div class="rule-row rule-row--banner" data-admin-row>
    <label class="field"><span>Заголовок</span><input data-field="title" maxlength="100" placeholder="Спеціальна пропозиція"></label><label class="field"><span>Товар і фото</span><select data-field="product_id"><option value="">Оберіть товар</option><?php foreach($catalogData['products'] as $product):?><option value="<?=h($product['id'])?>"><?=h($product['name'])?></option><?php endforeach;?></select></label><label class="field"><span>Посилання</span><input data-field="link" value="#menu"></label><label class="field"><span>Початок</span><input data-field="start" type="date"></label><label class="field"><span>Завершення</span><input data-field="end" type="date"></label><label class="switch-row"><input data-field="active" type="checkbox"><span>Активний</span></label><button class="text-button text-button--danger rule-remove" type="button" data-remove-admin-row>Видалити</button>
  </div></template>
  <template id="admin-product-option-template"><div class="option-admin-row" data-product-option-row><input type="hidden" data-option-field="id"><label class="field"><span>Назва</span><input data-option-field="name" maxlength="80" placeholder="Соєвий соус"></label><label class="field"><span>Ціна, грн</span><input data-option-field="price" inputmode="decimal" placeholder="20.00"></label><button class="text-button text-button--danger" type="button" data-remove-product-option>Видалити</button></div></template>

  <section class="admin-section" id="backups"><div class="admin-section-head"><div><p class="eyebrow">Приватне сховище</p><h2>Резервні копії</h2></div><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><button class="button" name="create_backup" value="1">Створити копію</button></form></div><p class="form-note">Зберігаються останні 30 перевірених копій каталогу та налаштувань. Секрети та персональні дані клієнтів до них не входять.</p><?php if(!$backups):?><p class="empty-state">Копій ще немає.</p><?php else:?><div class="backup-list"><?php foreach($backups as $backup):?><form method="post" class="backup-row"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><div><strong><?=h($backup['name'])?></strong><small><?=h((string)$backup['created_at'])?> · <?=number_format($backup['size']/1024,1,',',' ')?> КБ</small></div><label><span class="sr-only">Введіть ВІДНОВИТИ</span><input class="admin-input" name="restore_confirmation" placeholder="ВІДНОВИТИ" autocomplete="off" required></label><button class="text-button text-button--danger" name="restore_backup" value="<?=h($backup['name'])?>">Відновити</button></form><?php endforeach;?></div><?php endif;?></section>

  <section class="admin-section" id="categories">
    <div class="admin-section-head"><div><p class="eyebrow">Структура меню</p><h2>Категорії</h2></div></div>
    <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><div class="category-admin-list"><?php foreach($catalogData['categories'] as $category):?><div class="category-admin-row"><input class="admin-input" name="category[<?=h($category['id'])?>][name]" value="<?=h($category['name'])?>" required><input class="admin-input" type="number" min="0" name="category[<?=h($category['id'])?>][order]" value="<?=(int)($category['order']??0)?>" aria-label="Порядок категорії"><button class="text-button text-button--danger" name="delete_category" value="<?=h($category['id'])?>" formnovalidate>Видалити</button></div><?php endforeach;?></div><p><button class="button" name="save_categories" value="1">Зберегти категорії</button></p></form>
    <form method="post" class="inline-add-form"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><label class="field"><span>Нова категорія</span><input name="category_name" maxlength="80" required placeholder="Наприклад, Напої"></label><button class="button" name="add_category" value="1">Додати</button></form>
  </section>

  <form method="post" enctype="multipart/form-data" class="admin-section" id="add-product"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="admin-section-head"><div><p class="eyebrow">Нова позиція</p><h2>Додати товар</h2></div><button class="button" name="add_product" value="1">Створити товар</button></div>
    <div class="admin-form-grid"><label class="field"><span>Назва *</span><input name="new_product[name]" maxlength="120" required></label><label class="field"><span>Категорія *</span><select name="new_product[category]" required><?php foreach($catalogData['categories'] as $category):?><option value="<?=h($category['id'])?>"><?=h($category['name'])?></option><?php endforeach;?></select></label><label class="field"><span>Ціна, грн</span><input name="new_product[price]" inputmode="decimal"></label><label class="field"><span>Фото * - JPEG, PNG або WebP, до 8 МБ</span><input type="file" name="product_image" accept="image/jpeg,image/png,image/webp" required></label></div>
    <p class="form-note">Новий товар створюється прихованим. Після заповнення складу, ваги та часу ввімкніть його в каталозі.</p>
  </form>

  <form method="post" enctype="multipart/form-data" class="admin-section" id="catalog"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
    <div class="admin-section-head"><div><p class="eyebrow"><?=count($catalogData['products'])?> позицій</p><h2>Каталог</h2></div><button class="button" name="save_catalog" value="1">Зберегти каталог</button></div>
    <div class="admin-products"><?php foreach($catalogData['products'] as $product): ?>
      <details class="admin-product"><summary><img src="<?=h(product_image($product))?>" alt="" width="80" height="60"><span><strong><?=h($product['name'])?></strong><small><?=h(money($product['price_minor']??null))?></small></span><span class="availability <?=($product['available']??false)?'is-available':''?>"><?=($product['available']??false)?'Доступний':'Прихований'?></span></summary>
        <div class="admin-product-fields">
          <label class="field field--full"><span>Назва</span><input name="product[<?=h($product['id'])?>][name]" value="<?=h($product['name'])?>" required></label>
          <label class="field"><span>Категорія</span><select name="product[<?=h($product['id'])?>][category]"><?php foreach($catalogData['categories'] as $category):?><option value="<?=h($category['id'])?>" <?=$product['category']===$category['id']?'selected':''?>><?=h($category['name'])?></option><?php endforeach;?></select></label>
          <label class="field"><span>Ціна, грн</span><input inputmode="decimal" name="product[<?=h($product['id'])?>][price]" value="<?=h(money_input($product['price_minor']??null))?>"></label>
          <label class="field"><span>Акційна ціна, грн</span><input inputmode="decimal" name="product[<?=h($product['id'])?>][promo_price]" value="<?=h(money_input($product['promo_price_minor']??null))?>"></label>
          <div class="field"><span>Період акції</span><div class="date-pair"><input type="date" name="product[<?=h($product['id'])?>][promo_start]" value="<?=h($product['promo_start']??'')?>"><input type="date" name="product[<?=h($product['id'])?>][promo_end]" value="<?=h($product['promo_end']??'')?>"></div></div>
          <label class="field field--full"><span>Короткий опис</span><textarea name="product[<?=h($product['id'])?>][description]"><?=h($product['description']??'')?></textarea></label>
          <label class="field field--full"><span>Склад</span><textarea name="product[<?=h($product['id'])?>][ingredients]"><?=h($product['ingredients']??'')?></textarea></label>
          <label class="field"><span>Вага, г</span><input type="number" min="1" name="product[<?=h($product['id'])?>][weight_g]" value="<?=h((string)($product['weight_g']??''))?>"></label>
          <label class="field"><span>Приготування, хв</span><input type="number" min="1" name="product[<?=h($product['id'])?>][prep_minutes]" value="<?=h((string)($product['prep_minutes']??''))?>"></label>
          <label class="field"><span>Порядок</span><input type="number" min="0" name="product[<?=h($product['id'])?>][order]" value="<?=(int)($product['order']??0)?>"></label>
          <label class="field"><span>Мітка</span><select name="product[<?=h($product['id'])?>][badge]"><option value="">Без мітки</option><option value="Новинка" <?=($product['badge']??'')==='Новинка'?'selected':''?>>Новинка</option><option value="Хіт продажів" <?=in_array(($product['badge']??''),['Хіт продажів','Хіт'],true)?'selected':''?>>Хіт продажів</option></select></label>
          <div class="field field--full product-options-editor" data-product-options-editor="<?=h($product['id'])?>"><div class="field-heading"><span>Додаткові опції</span><button class="text-button" type="button" data-add-product-option="<?=h($product['id'])?>">＋ Додати опцію</button></div><div class="option-admin-list" data-product-option-rows><?php $optionRows=product_options($product);if(!$optionRows)$optionRows=[[]];foreach($optionRows as $optionIndex=>$option):?><div class="option-admin-row" data-product-option-row><input type="hidden" name="product[<?=h($product['id'])?>][options][<?=h((string)$optionIndex)?>][id]" value="<?=h($option['id']??'')?>"><label class="field"><span>Назва</span><input name="product[<?=h($product['id'])?>][options][<?=h((string)$optionIndex)?>][name]" value="<?=h($option['name']??'')?>" maxlength="80" placeholder="Соєвий соус"></label><label class="field"><span>Ціна, грн</span><input inputmode="decimal" name="product[<?=h($product['id'])?>][options][<?=h((string)$optionIndex)?>][price]" value="<?=h(money_input($option['price_minor']??null))?>" placeholder="20.00"></label><button class="text-button text-button--danger" type="button" data-remove-product-option>Видалити</button></div><?php endforeach;?></div></div>
          <div class="field field--full recommendation-picker"><div class="field-heading"><span>Рекомендовані товари</span><small><strong data-recommendation-count><?=count($product['recommendations']??[])?></strong> вибрано</small></div><input type="search" data-recommendation-search placeholder="Знайти товар у списку…" aria-label="Пошук рекомендованих товарів"><div class="recommendation-list"><?php foreach($catalogData['products'] as $candidate):if($candidate['id']===$product['id'])continue;?><label data-recommendation-item><input type="checkbox" name="product[<?=h($product['id'])?>][recommendations][]" value="<?=h($candidate['id'])?>" <?=in_array($candidate['id'],$product['recommendations']??[],true)?'checked':''?>><span><?=h($candidate['name'])?></span></label><?php endforeach;?></div></div>
          <label class="field field--full"><span>Замінити фото - JPEG, PNG або WebP, до 8 МБ</span><input type="file" name="product_image[<?=h($product['id'])?>]" accept="image/jpeg,image/png,image/webp"></label>
          <label class="switch-row field--full"><input type="checkbox" name="product[<?=h($product['id'])?>][available]" <?=($product['available']??false)?'checked':''?>><span>Показувати на сайті та у QR-меню</span></label>
          <div class="admin-product-actions field--full"><a class="text-link" href="/#product-<?=h($product['id'])?>" target="_blank" rel="noopener">Переглянути на сайті ↗</a><button class="text-button text-button--danger" name="archive_product" value="<?=h($product['id'])?>" formnovalidate>Перемістити в архів</button></div>
        </div>
      </details>
    <?php endforeach;?></div>
  </form>
  <?php if(!empty($catalogData['archived_products'])): ?><section class="admin-section"><div class="admin-section-head"><div><p class="eyebrow">Відновлення або остаточне видалення</p><h2>Архів товарів</h2></div></div><p class="form-note">Остаточне видалення прибирає пов’язані рекомендації, банери та подарунок. Перед операцією автоматично створюється резервна копія.</p><div class="archived-list"><?php foreach($catalogData['archived_products'] as $product):?><form method="post" class="archived-row"><input type="hidden" name="csrf" value="<?=h(csrf_token())?>"><span><?=h($product['name'])?></span><div class="archived-actions"><input class="admin-input" name="delete_product_confirmation" placeholder="ВИДАЛИТИ" autocomplete="off" aria-label="Підтвердження остаточного видалення <?=h($product['name'])?>"><button class="text-button" name="restore_product" value="<?=h($product['id'])?>" formnovalidate>Відновити</button><button class="text-button text-button--danger" name="delete_archived_product" value="<?=h($product['id'])?>">Видалити назавжди</button></div></form><?php endforeach;?></div></section><?php endif;?>
<?php endif;?></main><script src="/assets/js/admin.js" defer></script></body></html>
