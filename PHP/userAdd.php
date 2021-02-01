<?php
/* 11.11.2020
 * Добавление пользователя и соотв. ему аккаунта в блокчейн.
 * Аккаунтов у пользователя м.б. много, но пока ограничимся одним.
 * Скрипт м.б. вызван ли пользователем, либо админом.
 * В последнем случае м.б. указан id пользователя, которого нужно создать.
 */

// Включаемые модули
require_once './initApp.php';

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

$isAdmin = Utils\IsAdmin();
if ($isAdmin) {
  if ($_GET['userId']) {
    $userId = intval($_GET['userId']);
    if ($userId <= 0) {
      echo 'Id пользователя должен быть больше нуля';
      require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
      exit();
    }
    else {
      $bcParams = array('userId' => $userId);
    }
  }
}
else {
  $bcParams = null;
}
Logger\log_debug('userAdd.php. Входные параметры проверены');

$bc = new CBlockchain($bcParams);
$bc->userAdd();
if (!$bc->accountAdd()) {
  echo 'Кошелёк уже существует<br>';
}
else {
  echo 'Кошелёк успешно создан!<br>';
  if (!$isAdmin) {
    echo 'Кошелёк пополняет владелец услуг.<br>';
  }
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>