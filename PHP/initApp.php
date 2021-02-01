<?php
/* 23.11.2020
 * Загрузка модулей, общих для всех скриптов.
 * Инициализация приложения.
 * Если определена константа SILENT_MODE, html-вывод подавляется.
 * Этот режим используется в скриптах, выдающих только полезный
 * результат для вызывающего JavaScrypt.
 * В противном случае данный скрипт загружает заголовок страницы.
 * В тихом режиме при ошибке скрипт возвращает текст ошибки,
 * который JavaScrypt отобразит пользователю.
 */

/* Включаем буферизацию html-вывода, который может случайно возникнуть.
 * В конце этого скрипта очистим этот буфер.
 * Т.о. гарантировано вернем клиенту только результат, ожидаемый JavaScript.
 */
// Включаем буферизацию вывода
if (defined('SILENT_MODE')) {
  ob_start();
}

// Массив констант
$arrConst = require_once './const.php';
// Массив настроек
$arrSettings = require_once './.settings.php';
// Функции логирования
require_once './logger.php';
// Работа с cURL
require_once './curlPost.php';
// Класс работы с блокчейн
require_once './CBlockchain.php';
// Утилиты
require_once './utils.php';

/********************* Инициализация *******************/
use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

// Инициализация уровня логирования
$debugLevel = $arrSettings['debug_level'];
if (!$debugLevel) {
  $debugLevel = 'Error';
}

if (defined('SILENT_MODE')) {
  // Эти два скрипта не создают html-вывод, который всё равно попадёт в буфер
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/bx_root.php");
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
}
else {
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
}

if ( !$arrSettings || !$arrConst) {
  Logger\log_error('initApp.php. Инициализация: ошибка считывания настроек');
  if (defined('SILENT_MODE')) {
    // Очищаем буфер перед выдачей результата вызывающему скрипту
    ob_end_clean();
    return 'Ошибка считывания настроек';
  }
  else {
    exitErrorActions();
    exit; 
  }
}
if (!$APPLICATION || !$USER || !$DB) {
  Logger\log_error('initApp.php. Классы Битрикса не инициализированы');
  if (defined('SILENT_MODE')) {
    ob_end_clean();
    return 'Ошибка инициализации приложения';
  }
  else {
    exitErrorActions();
	  exit;
  }
}

if (!defined('SILENT_MODE')) {
  $APPLICATION->SetTitle($arrConst['application_title']);
  // Эти стили подключаются в главной странице personal/index.php
  $APPLICATION->SetAdditionalCSS('/bitrix/css/main/bootstrap.css');
}

// проверка авторизации
if (!$USER->IsAuthorized()) {
  Logger\log_debug('initApp.php. Требуется авторизация пользователя');
  if (defined('SILENT_MODE')) {
    ob_end_clean();
    return 'Требуется авторизация пользователя. Перезагрузите, пожалуйста, страницу';
  }
  else {
	  // загрузка формы авторизации
	  $APPLICATION->ShowAuthForm("");
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
	  exit;
  }
}
Logger\log_debug('initApp.php. Инициализация завершена, пользователь авторизован. '.
                 'bitrix_sessid: '.bitrix_sessid());


/**************************** Функции ******************************/
// Действие при ошибке, выполняемые перед вызовом exit
function exitErrorActions() {
  echo 'Что-то пошло не так...';
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}


/* С помощью функции set_exception_handler() можно задать функцию, 
 * которая будет выполнена вместо блока catch, если не нашлось подходящего.
 * Как будто мы всю программу обернули в блок try-catch, 
 * где за реализацию блока catch отвечает установленная функция
 */
set_exception_handler('exception_handler');

// Установка обработчика ошибок по умолчанию.
// После вызова этой функции выполнение скрипта будет остановлено.
function exception_handler($exception) {
  Logger\log_error('Необработанное исключение: '.$exception->getMessage().
                   ' in '.$exception->getFile().
                   ' on line '.$exception->getLine().
                   ', trace: '.$exception->getTraceAsString() );
  //echo 'Необработанное исключение: '.$exception->getMessage().'<BR>';
  if (defined('SILENT_MODE')) {
    return 'Что-то пошло не так...';
  }
  else {
    exitErrorActions();
  }
}

// очищаем буфер, который мог образоваться в результате включения скриптов
if (defined('SILENT_MODE')) {
  ob_end_clean();
  return 'OK';
}

?>