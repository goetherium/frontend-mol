<?php
namespace BlockChain\Logger;

/* 11.11.2020
 * Логирование ошибок, отладки для приложения.
 * Переменная $debugLevel определена во включающем скрипте.
 */


// Логирование ошибок
function log_error($err_msg) {
  global $debugLevel;
  global $USER;
  $userId;

  $now = date('Y-m-d H:i:s');
  if ($USER) {
    $userId = intval($USER->GetID());
  }
  
  error_log($now.' userId '.$userId.': '.$err_msg."\n", 3, './logs/errors.log');
  /*if ($debugLevel==='Error' || $debugLevel==='Warn' ||
      $debugLevel==='Info'  || $debugLevel==='Debug')
  {
    error_log($now.' userId '.$userId.': '.$err_msg."\n", 3, './logs/errors.log');
  }*/
}

// Логирование отладочных сообщений
function log_debug($debug_msg) {
  global $debugLevel;
  $now = date('Y-m-d H:i:s');
  
  if ($debugLevel==='Debug')
  {
    global $USER;
    $userId;
    if ($USER) {
      $userId = intval($USER->GetID());
    }
    error_log($now.' userId '.$userId.': '.$debug_msg."\n", 3, './logs/debug.log');
  }
}

/*
// Логирование предупреждающих сообщений
function log_warn($warn_msg) {
  global $debugLevel;
  $now = date('Y-m-d H:i:s');
  
  if ($debugLevel==='Warn' ||
      $debugLevel==='Info' || 
      $debugLevel==='Debug')
  {
    error_log($now.' '.$warn_msg."\n", 3, './logs/warns.log');
  }
}

// Логирование информационных сообщений
function log_inform($info_msg) {
  global $debugLevel;
  $now = date('Y-m-d H:i:s');
  
  if ($debugLevel==='Info' || $debugLevel==='Debug')
  {
    error_log($now.' '.$info_msg."\n", 3, './logs/info.log');
  }
}
*/

?>