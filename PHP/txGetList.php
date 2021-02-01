<?php
/* 31.12.2020
 * Возвращает список транзакций пользователя в виде готовой HTML-таблицы.
 * Вызывается из формы отбора списка транзакций.
 * Вызывается из JavaScrypt, поэтому работаем в тихом режиме,
 * без выдачи стандартной шапки и подвала
 * Вызывающий скрипт встраивает HTML-таблицу в свою страницу.
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

/***** Проверка данных вызывающей HTML-формы *****/
$isAdmin = Utils\IsAdmin();
// Если запрос выполняет админ, в скрипт д.б. передан id пользователя
if ($isAdmin) {
  if (!isset($_POST['userId'])) {
    echo 'Не задан id пользователя';
    exit;
  }
  $userId = intval($_POST['userId']);
  if ($userId <= 0) {
    echo 'Id пользователя должен быть больше нуля';
    exit;
  }
}

// Заполним параметры отбора транзакций
$arrParams = array();

// исх. транзакции по умолчанию
$direction = 'Out';
if (!empty($_POST['direction'])) {
  $direction = htmlentities($_POST['direction'], ENT_QUOTES);
  if (! ($direction === 'Out' || $direction === 'In') ) {
    echo '<h4>Неверное значение направления транзакций</h4>';
    exit;
  }
}
$arrParams['direction'] = $direction;

$dateFrom;
if (!empty($_POST['dateFrom'])) {
  try {
    $dateFrom = new DateTime(htmlentities($_POST['dateFrom'], ENT_QUOTES));
  }
  catch (Exception $e) {
    echo '<h4>Неверное значение начальной даты</h4>';
    exit;
  }
  // Передавать время нужно в зоне UTC
  $arrParams['dateFrom'] = Utils\ConvertLocalDate2UTCStr($dateFrom);
}

if (!empty($_POST['dateTo'])) {
  try {
    $dateTo = new DateTime(htmlentities($_POST['dateTo'], ENT_QUOTES));
  }
  catch (Exception $e) {
    echo '<h4>Неверное значение конечной даты</h4>';
    exit;
  }
  // Форма передает дату без времени, получим последнюю секунду даты
  $dateTo->modify('+1 day');
  $dateTo->modify('-1 sec');
  $arrParams['dateTo'] = Utils\ConvertLocalDate2UTCStr($dateTo);
}

if (!empty($_POST['sortOrder'])) {
  $arrParams['sortOrder'] = htmlentities($_POST['sortOrder'], ENT_QUOTES);
  if (! ($arrParams['sortOrder'] === 'Asc'  || 
         $arrParams['sortOrder'] === 'Desc' ||
         $arrParams['sortOrder'] === 'NoSort')
     )
  {
    echo '<h4>Неверное значение параметра сортировки транзакций</h4>';
    exit;
  }
}
else {
  $arrParams['sortOrder'] = 'Desc';
}
Logger\log_debug('txGetList.php. Входные параметры проверены');


if ($isAdmin) {
  // Объект блокчейна будет инициализирован id пользователя
  $bc = new CBlockchain(array('userId' => $userId));
}
else {
  // Объект блокчейна будет инициализирован текущим пользователем
  $bc = new CBlockchain();
}
// Проверим аккаунт пользователя
$bcAccountExists = false;
if ($bc->userGet()) {
  // Получим основной аккаунт пользователя
  $firstAccountUser = $bc->accountGet()[0];
  if ($firstAccountUser) {
    $bcAccountExists = true;
  }
}
if (!$bcAccountExists) {
  echo 'Кошелёк не создан';
  exit;
}

$arrParams['accountAddress'] = $firstAccountUser['accountAddress'];
// Получим список транзакций
$txList = $bc->txGetList($arrParams);
if (!$txList) {
  echo '<h4>Транзакций не найдено</h4>';
  exit;
}

//echo json_encode($txList);

/* Выведем HTML с готовой таблицей транзакций, которую javascrypt вставит в документ.
 * Стиль таблицы из /bitrix/css/main/bootstrap.css
 * Используется стиль "clicableTable.css" для изменения формы указателя
 * и подстветки текущей строки таблицы транзакций. 
 * Стиль подключается в вызывающем скрипте.
 * Для правильного применения стиля к строке, тело таблицы нужно включить в тег tbody.
 */ 
?>
<table class="table">
  <caption>Список транзакций</caption>
  <thead>
    <tr>
<?php
// отображаем логин и ФИО только админам
if ($isAdmin) {
  if ($direction == 'Out') {
    echo '<th>Логин получателя</th>';
    echo '<th>ФИО получателя</th>';
  }
  else {
    echo '<th>Логин отправителя</th>';
    echo '<th>ФИО отправителя</th>';
  }
}
?>
      <th>Сумма, <?=$arrConst['blockchain_value_name_genitive']?></th>
      <th>Дата</th>
      <th>Хеш транзакции</th>
    </tr>
  </thead>
  <tbody>

<?php
/* Пример транзакции:
{
 "sourceUserLogin":"dlukyant",
 "destUserLogin":"dlukyant@gmail.com",
 "txHash":"0xb349a96a5c42a1f97ef6f27f55e53e3bc5dbb7097ad4ba111d59118fd12315bf",
 "sourceAddress":"0xD8eD4e25fe9574E9Ea3Fcb4Ce4c1d9740AA72731",
 "destAddress":"0x70Cf3940CDa05eEaCfeb111461bA60029d6a4dED",
 "txCreatedDate":"2020-12-28T09:03:14.000Z",
 "weiValue":"10000000000000000",
 "etherValue":"0.01"
}
*/
foreach ($txList as &$tx) {
  // В теге строки укажем хеш транзакции - для открытия окна с деталями
  // транзакции при нажатии на строку таблицы с помошью JavaScript.
  // Класс bc-pointer стиля изменяет указатель мыши и подсветку строки
  echo "<tr id='{$tx['txHash']}' class='bc-pointer'>";

  // Логин и ФИО отображаем только админу
  if ($isAdmin) {
    if ($direction == 'Out') {
      echo "<td>{$tx['destUserLogin']}</td>";
    }
    else {
      echo "<td>{$tx['sourceUserLogin']}</td>";
    }
    if ($direction == 'Out') {
      $userLogin2Find = $tx['destUserLogin'];
    }
    else {
      $userLogin2Find = $tx['sourceUserLogin'];
    }
    // Если логина нет на сайте, нужно сбросить ФИО
    $userFIO = 'Незарегистрированный пользователь';
    if (!empty($userLogin2Find)) {
      $rsUser = CUser::GetByLogin($userLogin2Find);
      if ($arUser = $rsUser->Fetch()) {
        $userFIO = $arUser['LAST_NAME'].' '.$arUser['NAME'].' '.$arUser['SECOND_NAME'];
      }
    }
    echo "<td>{$userFIO}</td>";  // заголовок есть, так что выводим всегда
  }
  echo "<td title = 'Сумма транзакции'>{$tx['etherValue']}</td>";
  echo "<td title = 'Дата включения транзакции в блокчейн'>".
       Utils\ConvertUTCStr2LocalDate($tx['txCreatedDate'])->format('d.m.Y H:i:s')."</td>";  
  echo "<td title = 'Уникальный идентификатор транзакции в блокчейне'>".
       substr($tx['txHash'],0,6)."...".substr($tx['txHash'],-6,6)."</td>";
  echo '</tr>';
}
echo '</tbody>';
echo '</table>';
?>
