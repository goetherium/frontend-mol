<?php

/* 10.11.2020
 * Включаемый файл констант проекта
 */

return array (
  // корень скриптов данного проекта
  'api_root' => '/api',  // указать /personal/blockchain для прода и /api для разработки
  // Заголовок страниц раздела блокчейна
  'application_title' => 'Блокчейн',

  // адрес API блокчейна
  'jsonrpc_url' => 'https://my.alladin.host',
  // версия протокола JSON-RPC
  'jsonrpc_version' => '2.0',
  // реалм сайта для указания в запросах в backend
  'jsonrpc_realm' => 'mol',
  // название учетной записи по умолчанию
  'jsonrpc_default_account_name' => 'Основной аккаунт',

  // именительный падеж значений условных единиц блокчейна
  'blockchain_value_name_nominative' => 'алладин',
  'blockchain_value_name_plural' => 'алладины',
  // родительный падеж значений блокчейна
  'blockchain_value_name_genitive' => 'алладинов',
);
?>
