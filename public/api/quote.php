<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')send_json(['error'=>'Метод не підтримується.'],405);
$raw=file_get_contents('php://input');if($raw===false||strlen($raw)>30000)send_json(['error'=>'Запит завеликий.'],413);
try{$input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(Throwable){send_json(['error'=>'Некоректний формат запиту.'],400);}
if(!is_array($input))send_json(['error'=>'Некоректний запит.'],400);
try{$quote=calculate_order_quote($input['items']??[],read_json('catalog.json'),settings(),(string)($input['fulfillment']??'pickup'),(string)($input['promo_code']??''));send_json(['ok'=>true]+$quote);}catch(InvalidArgumentException $exception){send_json(['error'=>$exception->getMessage()],422);}
