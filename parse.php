<?php

use App\Parser;

define('MODX_API_MODE', true);
require_once dirname(__DIR__, 3) . '/index.php';
$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
include_once 'vendor/autoload.php';

$categoryParser = Parser::create('category.log');

$categoryParser
    ->log('Начинаю парсинг категорий')
    ->process()
    ->getResult()
;


dd($categoryParser);