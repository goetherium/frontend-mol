<?php
/* 09.12.2020
 * Отображение деталей транзакции.
 * Вызывается из формы отбора транзакции.
 * Д.б. задан GET-параметр txHash
 */

// Инициализация приложения, загрузка модулей и заголовка страницы
require_once './initApp.php';

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

// Пример хеша транзакции: 
// 0x61cf93da0e1d15b40597c910a225b167b6ec430fa1fc06bad4c421e0ec40963b
if (!isset($_GET['txHash'])) {
  echo 'Не задан хеш транзакции';
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
  exit;
}
// Такой фильтр не сработает, т.к. хеш транзакции для него слишком большое число
// filter_var($_GET['txHash'], FILTER_VALIDATE_INT, array('flags' => FILTER_FLAG_ALLOW_HEX)
$errMsg;
$txHash = htmlentities($_GET['txHash'], ENT_QUOTES);
$pattern = '/[xX0-9a-fA-F]/';
$replacement = '';
$checkHash = preg_replace($pattern, $replacement, $txHash);
if ($checkHash) {
  $errMsg = "Недопустимые символы в хеше транзакции";
}
if (strlen($txHash)!=66) {
  $errMsg = 'Длина хеша транзакции должна быть 66 hex-символов, включая префикс 0x';
}
if (!empty($errMsg)) {
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
  exit;
}
Logger\log_debug('txGet.php. Входной параметр - хеш транзакции проверен');

// Получим транзакцию из блокчейна
if (!$bc) $bc = new CBlockchain();
$tx = $bc->txGet($txHash);
if (!$tx) {
  throw new Exception("txGet.php. Для хеша $txHash транзакция не найдена");
}
?>

<!-- стиль из /bitrix/css/main/bootstrap.css -->
<table class="table">
<caption>Детали транзакции</caption>
<tr>
  <th>Атрибут</th>
  <th>Значение</th>
</tr>

<?php
/* Пример транзакции
 * {"jsonrpc":"2.0","id":5,"result":{
 * "hash":"0x8adb5b0cc97401600e50ee0c055b592b389419edd31e18eb7bd1a42d1dcdcf71",
 * "nonce":9,
 * "blockHash":"0xf44bdc6bb1c1db2ed289f266703b103c262f7b5d16dfbe3d814832bb47de5657",
 * "blockNumber":1333991,
 * "transactionIndex":0,
 * "from":"0xC4d7AdA8Dc2212a21A53cF430E9E3e789e59A234",
 * "to":"0xCfEDa4919c8971f0581B12D76d5d85F3674651f5",
 * "value":"0.1",
 * "gasUsed":"0.000000000000021",
 * "gasPrice":"0.000000000000000001",
 * "input":"0x",
 * "sourceUserLogin":"0x70Cf3940CDa05eEaCfeb111461bA60029d6a4dED",
 * "destUserLogin":"0xD8eD4e25fe9574E9Ea3Fcb4Ce4c1d9740AA72731"
 * }}
 */

$isAdmin = Utils\IsAdmin();
if ($isAdmin) {
  echo '<tr>';
  echo "<td>Логин отправителя</td>";
  echo "<td>{$tx['sourceUserLogin']}</td>";
  echo '</tr>';

  // Получение ФИО пользователя из БД сайта по логину
  $sourceFIO = 'Незарегистрированный пользователь';
  if (!empty($tx['sourceUserLogin'])) {
    $rsUser = CUser::GetByLogin($tx['sourceUserLogin']);
    if ($arUser = $rsUser->Fetch()) {
      $sourceFIO = $arUser['LAST_NAME'].' '.$arUser['NAME'].' '.$arUser['SECOND_NAME'];
    }
  }
  echo '<tr>';
  echo "<td>ФИО отправителя</td>";
  echo "<td>{$sourceFIO}</td>";
  echo '</tr>';
}
echo '<tr>';
echo "<td title = 'Уникальный индентификатор отправителя в блокчейне'>Адрес отправителя</td>";
echo "<td>{$tx['from']}</td>";
echo '</tr>';
if ($isAdmin) {
  echo '<tr>';
  echo "<td>Логин получателя</td>";
  echo "<td>{$tx['destUserLogin']}</td>";
  echo '</tr>';
  $destFIO = 'Незарегистрированный пользователь';
  if (!empty($tx['destUserLogin'])) {
    $rsUser = CUser::GetByLogin($tx['destUserLogin']);
    if ($arUser = $rsUser->Fetch()) {
      $destFIO = $arUser['LAST_NAME'].' '.$arUser['NAME'].' '.$arUser['SECOND_NAME'];
    }
  }
  echo '<tr>';
  echo "<td title = 'ФИО на сайте'>ФИО получателя</td>";
  echo "<td>{$destFIO}</td>";
  echo '</tr>';
}
echo '<tr>';
echo "<td title = 'Уникальный индентификатор получателя в блокчейне'>Адрес получателя</td>";
echo "<td>{$tx['to']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td>Сумма транзакции</td>";
echo "<td>{$tx['value']} {$arrConst['blockchain_value_name_genitive']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td title = 'Уникальный индентификатор транзакции в блокчейне'>Хеш транзакции</td>";
echo "<td>{$tx['hash']}</td>";  // substr($tx['hash'],0,10)."...".substr($tx['hash'],-10,10)
echo '</tr>';
echo '<tr>';
echo "<td title = 'Сколько транзакций было сделано с данного адреса отправителя'>Порядковый номер транзакции отправителя</td>";
echo "<td>{$tx['nonce']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td title = 'Уникальный индентификатор блока в блокчейне'>Хеш блока</td>";
echo "<td>{$tx['blockHash']}</td>";  // substr($tx['blockHash'],0,10)."...".substr($tx['blockHash'],-10,10)
echo '</tr>';
echo '<tr>';
echo "<td title = 'Высота блока в блокчейне'>Номер блока</td>";
echo "<td>{$tx['blockNumber']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td>Порядковый номер транзакции в блоке</td>";
echo "<td>{$tx['transactionIndex']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td title = 'Плата за включение транзакции в блокчейн'>Использовано газа для выполнения транзакции</td>";
echo "<td>{$tx['gasUsed']} {$arrConst['blockchain_value_name_genitive']}</td>";
echo '</tr>';
echo '<tr>';
echo "<td title = 'Сигнатура вызова функции смарт-контракта'>Входные данные транзакции</td>";
echo "<td>{$tx['input']}</td>";
echo '</tr>';

echo '</table>';

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>