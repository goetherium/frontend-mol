<?php
/* 28.12.2020
 * Проведение транзакции в блокчейн пользователем или админом.
 * Вызывается из HTML-формы txSendAdmin.php и  txSendUser.php
 * Если скрипт вызван админом, д.б. задан POST-параметр userId
 * Вызывается из JavaScrypt, поэтому работаем в тихом режиме,
 * без выдачи стандартной шапки и подвала страницы.
 * Вызывающий скрипт выводит результат в свою страницу.
*/
// Объявляем тихий резим
define('SILENT_MODE', TRUE);
// Получаем результат инициализации в тихом режиме
$initRes = include 'initApp.php';
if ($initRes!=='OK') {
  echo $initRes;
  exit;
}

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

// Если транзакцию отправляет админ, д.б. задан id пользователя
$isAdmin = Utils\IsAdmin();
if ($isAdmin) {
  if (empty($_POST['userId'])) {
    echo 'Не задан id пользователя';
    exit;
  }
  $userId = intval($_POST['userId']);
  if ($userId <= 0) {
    echo 'Id пользователя должен быть больше нуля';
    exit;
  }
}
// Проверка суммы транзакции
if (empty($_POST['txValue'])) {
  echo 'Не задана сумма транзакции';
  exit;
}
// Фильтр проверяет, что значение является корректным числом с плавающей запятой
if (filter_var($_POST['txValue'], 
                FILTER_VALIDATE_FLOAT, 
                array('options' => array('min_range' => 0))
              ) == FALSE) 
{
  echo 'Сумма транзакции должна быть больше нуля';
  exit;
}
$txValue = floatval($_POST['txValue']);

Logger\log_debug('txSend.php. Входные параметры проверены');
// Инициализируем объект блокчейна пользователем
if ($isAdmin) {
  // Пользователь будет получателем
  $bcUser = new CBlockchain( array('userId' => $userId) );
}
else {
  // Пользователь будет отправителем
  $bcUser = new CBlockchain();
}
// Проверка существования кошелька пользователя
$bcAccountExists = false;
if ($bcUser->userGet()) {
  // Получаем основной аккаунт пользователя
  $firstAccountUser = $bcUser->accountGet()[0];
  if ($firstAccountUser) {
    $bcAccountExists = true;
  }
}
if (!$bcAccountExists) {
  if ($isAdmin) {
    echo 'Кошелёк пользователя не создан';
  }
  else {
    echo 'Ваш кошелёк не создан';
  }
  exit;
}

// Если транзакцию отправляет админ, получим адрес его аккаунта
if ($isAdmin) {
  $bcAccountExists = false;
  $bcAdmin = new CBlockchain();
  if ($bcAdmin->userGet()) {
    // Получаем основной аккаунт админа
    $firstAccountAdmin = $bcAdmin->accountGet()[0];
    if ($firstAccountAdmin) {
      $bcAccountExists = true;
    }
  }
  if (!$bcAccountExists) {
    echo 'Кошелёк администратора не создан';
    exit;
  }
}

/* Проведение транзакции в блокчейне:
 * админ отправляет транзакцию пользователю, переданному при вызове скрита,
 * а пользователь отправляет транзакцию владельцу сайта.
 */
if ($isAdmin) {
  Logger\log_debug('txSend.php. Параметры транзакции админа:'.
        '  адреса отправителя (админа) '.$firstAccountAdmin['accountAddress'].
        ', адрес получателя (клиента)  '.$firstAccountUser['accountAddress'].
        ', сумма транзакции '.$txValue);
  $txParams = array('accountAddress'     => $firstAccountAdmin['accountAddress'],
                    'destinationAddress' => $firstAccountUser['accountAddress'],
                    'txValue'            => $txValue,
  );
  // Проводим транзакцию в блокчейне
  $arResult = $bcAdmin->txSend($txParams);
}
else {
  // Адрес аккаунта владельца сайта
  $ownerAddress = Utils\getOwnerAddress();
  Logger\log_debug('txSend.php. Параметры транзакции пользователя:'.
    '  адреса отправителя (клиента) '.$firstAccountUser['accountAddress'].
    ', адрес получателя (владельца) '.$ownerAddress.  // адрес владельца услуг
    ', сумма транзакции '.$txValue);
  $txParams = array('accountAddress'     => $firstAccountUser['accountAddress'],
                    'destinationAddress' => $ownerAddress,
                    'txValue'            => $txValue,
  );
  // Проводим транзакцию в блокчейне
  $arResult = $bcUser->txSend($txParams);
}
// Выдаём результат проведения транзакции
echo $arResult['USER_MSG'];

?>