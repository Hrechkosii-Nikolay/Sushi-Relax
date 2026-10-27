<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json(['error'=>'Метод не підтримується.'],405);
$raw=file_get_contents('php://input');
if($raw===false||strlen($raw)>12000)send_json(['error'=>'Запит завеликий.'],413);
try{$input=json_decode($raw,true,24,JSON_THROW_ON_ERROR);}catch(Throwable){send_json(['error'=>'Некоректний формат запиту.'],400);}
if(!is_array($input)||!empty($input['website']))send_json(['error'=>'Запит відхилено.'],400);
$requestId=preg_replace('/[^a-zA-Z0-9-]/','',(string)($_SERVER['HTTP_X_REQUEST_ID']??''));
if($requestId===''||strlen($requestId)>80)send_json(['error'=>'Не вдалося підтвердити унікальність замовлення. Оновіть сторінку.'],400);
$ip='Невідомо';
foreach([$_SERVER['HTTP_CF_CONNECTING_IP']??'',$_SERVER['REMOTE_ADDR']??'']as$candidate){$candidate=trim((string)$candidate);if(filter_var($candidate,FILTER_VALIDATE_IP)!==false){$ip=$candidate;break;}}
if(!in_array($ip,['127.0.0.1','::1'],true)&&!acquire_rate_limit('reservation:'.$ip,5,600))send_json(['error'=>'Забагато спроб. Зачекайте перед повторним надсиланням.'],429);

$site=settings();
$name=trim((string)($input['name']??''));
$phone=preg_replace('/[^0-9+()\- ]/u','',(string)($input['phone']??''));
$persons=filter_var($input['persons']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>20]]);
$date=(string)($input['date']??'');$time=(string)($input['time']??'');
$comment=trim((string)($input['comment']??''));
$timeOnPage=filter_var($input['time_on_page_seconds']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>86400]]);
if($timeOnPage===false)$timeOnPage=0;
if(mb_strlen($name)<2||mb_strlen($name)>80)send_json(['error'=>'Перевірте ім’я.'],422);
if(strlen(preg_replace('/\D/','',$phone))<9||mb_strlen($phone)>30)send_json(['error'=>'Перевірте номер телефону.'],422);
if($persons===false)send_json(['error'=>'Оберіть кількість гостей від 1 до 20.'],422);
if(mb_strlen($comment)>500)send_json(['error'=>'Коментар надто довгий.'],422);
if(($input['privacy']??false)!==true)send_json(['error'=>'Потрібна згода з політикою конфіденційності.'],422);
$timezone=new DateTimeZone('Europe/Kyiv');
$reservationAt=DateTimeImmutable::createFromFormat('!Y-m-d H:i',$date.' '.$time,$timezone);
$dateOnly=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$timezone);
if(!$reservationAt||!$dateOnly||$dateOnly->format('Y-m-d')!==$date||!in_array($time,available_slots($site,$dateOnly),true))send_json(['error'=>'Обрані дата або час уже недоступні. Оновіть список.'],422);

$reservationId=bin2hex(random_bytes(12));
$reservationNumber=strtoupper(substr(base_convert(substr(hash('sha256',$reservationId),0,12),16,36),0,7));
$createdAt=(new DateTimeImmutable('now',$timezone))->format('d.m.Y H:i');
$lines=[
    "🍽 БРОНЮВАННЯ СТОЛУ #{$reservationNumber}",
    '━━━━━━━━━━━━━━','',
    "👤 Ім’я: {$name}",
    "☎️ Телефон: {$phone}",
    "👥 Кількість гостей: {$persons}",
    '📅 Дата: '.$reservationAt->format('d.m.Y'),
    '⏰ Час: '.slot_label($site,$time),
];
if($comment!==''){$lines[]='';$lines[]="💬 Коментар:\n{$comment}";}
$lines[]='';$lines[]='━━━━━━━━━━━━━━';$lines[]="🌐 IP: {$ip}";$lines[]='⏱ На сторінці: '.max(0,(int)$timeOnPage).' с';$lines[]="🕒 Створено: {$createdAt}";
$message=implode("\n",$lines);
$token=app_secret('SUSHI_TELEGRAM_BOT_TOKEN','telegram_bot_token');
$chatId=app_secret('SUSHI_TELEGRAM_CHAT_ID','telegram_chat_id');
if($token===''||$chatId==='')send_json(['error'=>'Надсилання бронювань ще не налаштоване адміністратором.'],503);
if(!reserve_idempotency($requestId,$reservationId,$reservationNumber,hash('sha256',$raw))){
    $existing=read_idempotency($requestId);
    if(($existing['status']??'')==='delivered'&&!empty($existing['order_number'])&&hash_equals((string)($existing['payload_hash']??''),hash('sha256',$raw)))send_json(['ok'=>true,'reservation_number'=>$existing['order_number'],'duplicate'=>true]);
    send_json(['error'=>'Це замовлення вже обробляють. Не надсилайте його повторно одразу.'],409);
}
$ch=curl_init('https://api.telegram.org/bot'.rawurlencode($token).'/sendMessage');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['chat_id'=>$chatId,'text'=>$message],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
$response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
$decoded=is_string($response)?json_decode($response,true):null;
if($error!==''){technical_log('reservation.telegram_ambiguous',['http_status'=>$status]);send_json(['error'=>'Немає однозначного підтвердження доставки замовлення. Не повторюйте його одразу - зв’яжіться із закладом.'],502);}
if($status!==200||!is_array($decoded)||($decoded['ok']??false)!==true){release_idempotency($requestId,$reservationId);technical_log('reservation.telegram_rejected',['http_status'=>$status]);send_json(['error'=>'Не вдалося надіслати замовлення. Спробуйте пізніше або зателефонуйте нам.'],502);}
if(!mark_idempotency_delivered($requestId,$reservationId))technical_log('reservation.idempotency_mark_failed');
send_json(['ok'=>true,'reservation_number'=>$reservationNumber]);
