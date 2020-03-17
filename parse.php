<?php

use App\Parser;

//define('MODX_API_MODE', true);
//require_once dirname(__DIR__, 3) . '/index.php';
//$modx->getService('error','error.modError');
//$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
//$modx->setLogTarget('FILE');
include_once 'vendor/autoload.php';

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);


$categoryParser = Parser::create('CategoryParser.log', true);

$categoryParser = $categoryParser
    ->process()
;

$categoryParser->log('Окончание парсинга', $categoryParser->getResult());

dd($categoryParser->getResult());