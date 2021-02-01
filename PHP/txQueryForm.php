<?php
/* 09.12.2020
 * Отображение списка транзакций заданного или текущего пользователя.
 * Если скрипт вызывается из админской формы, д.б. указан параметр userId.
 * Если скрипт вызывается из пользовательской формы, userId не заполняется.
 * Скрипт: 
 * - подключает стиль для подстветки текущей строки таблицы транзакций;
 * - отображает форму для отбора транзакций;
 * - при нажатии на кнопку формы подгружает список транзакций и вставляет её в документ;
 * - при нажатии на строку таблицы в попап-окне открывает детали транзакции.
 */

// Инициализация приложения, загрузка модулей и заголовка страницы
require_once './initApp.php';

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

// Подключаем стиль для изменения формы указателя и подстветки текущей строки
// таблицы транзакций, которая будет получена при нажатии кнопки формы
use Bitrix\Main\Page\Asset;
Asset::getInstance()->addCss("{$arrConst['api_root']}/css/txQueryForm.css");
Logger\log_debug('txQueryForm.php. Загружены стили таблицы транзакций');


$isAdmin = Utils\IsAdmin();
// Если запрос выполняет админ, в скрипт д.б. передан id пользователя
if ($isAdmin) {
  if (empty($_GET['userId'])) {
    echo 'Не задан id пользователя';
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
  }
  $userId = intval($_GET['userId']);
  if ($userId <= 0) {
    echo 'Id пользователя должен быть больше нуля';
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
    exit;
  }
}
Logger\log_debug('txQueryForm.php. Входные параметры проверены');

// Выводим ФИО пользователя
if ($isAdmin) {
  echo '<h3>Клиент: '.Utils\getUserName($userId).'</h3><br><br>';
}

if (isset($userId)) {
  // Объект блокчейна будет инициализирован id пользователя
  $bcParams = array('userId' => $userId);
}
else {
  // Объект блокчейна будет инициализирован текущим пользователем
  $bcParams = array();
}
// Инициализация блокчейна
$bc = new CBlockchain($bcParams);

// Проверим существование аккаунта пользователя
$bcAccountExists = false;
if ($bc->userGet()) {
  // Основной аккаунт пользователя
  $firstAccountUser = $bc->accountGet()[0];
  if ($firstAccountUser) {
    $bcAccountExists = true;
  }
}

if (!$bcAccountExists) {
  echo 'Кошелёк не создан<br>';
  // Админу предоставим возможность создать пользовательский кошелёк
  if ($isAdmin) {
    echo '<br>';
    echo "<a class='button' href='{$arrConst['api_root']}/userAdd.php?userId={$userId}'".
        ">Создать кошелёк клиенту</a>";
  }
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
  exit;
}
if ($firstAccountUser['balance']) {
  echo 'Баланс кошелька: '.round(floatval($firstAccountUser['balance']),2).' '.
       $arrConst['blockchain_value_name_genitive'].'<br>';
}
if ($firstAccountUser['createdDate']) {
  echo 'Дата создания кошелька: '.
        Utils\ConvertUTCStr2LocalDate($firstAccountUser['createdDate'])->format('d.m.Y').'<br>';
}
echo '<br><br>';

// даты фильтра по умолчанию со вчерашнего по текущий день
$dateFrom = new DateTime('now');
$dateFrom->modify('-1 day');
$dateFrom = $dateFrom->format('Y-m-d');
$dateTo = (new DateTime('now'))->format('Y-m-d');
?>

<!-- Отобразим фильтр отбора транзакций -->
<form id="txQueryForm">
  <!-- Группируем элемены формы в одну рамку -->
  <fieldset name="txQueryFilter">
    <legend>Отбор транзакций</legend>
    <p>
      <input type="hidden" id="userId" name="userId" value="<?=$userId?>">
      <select size="1" id="direction" name="direction">
        <option selected value="Out">Исходящие транзакции</option>
        <option value="In">Входящие транзакции</option>
      </select>
      <label for="dateFrom">С</label>
      <input type="date" id="dateFrom" name="dateFrom" value="<?=$dateFrom?>">
      <label for="dateTo">До</label>
      <input type="date" id="dateTo" name="dateTo" value="<?=$dateTo?>">
      <select size="1" id="sortOrder" name="sortOrder">
        <option selected value="Desc">Сначала новые</option>
        <option value="Asc">Сначала старые</option>
        <option value="NoSort">Без сортировки</option>
      </select>
    </p>
  </fieldset>
  <p><input type="submit" value="Посмотреть транзакции"></p>
</form>

<!-- В этот блок будет помещаться таблица с результатом отбора транзакций.
     Блок должен быть статическим, т.к. на него регистрируется событие
     onclick на строке включенной таблицы -->
<div id="txList">
</div>

<script>
// Ссылка на форму отбора транзакций
const jsTxQueryForm = document.getElementById("txQueryForm");
// Ссылка на блок, включающий таблицу транзакций.
const jsTxList = document.getElementById("txList");

/***** Запрос списка транзакций при нажатии кнопки в форме *****/
jsTxQueryForm.onsubmit = async (event) => {
  // Браузер не будет отправлять форму, мы сами отправим запрос
  event.preventDefault();

  const response = await fetch(`<?=$arrConst['api_root']?>/txGetList.php`, {
    method: 'POST',
    body: new FormData(jsTxQueryForm),  // FormData получит все данные формы
  });

  // HTML с таблицей/ошибкой
  let resHtml;
  // Ссылка на динамически создаваемый блок с таблицей транзакций
  let divResHtml;

  // Если код ответа 200-299 ...
  if (response.ok) {
    // Получим готовый HTML в виде текста - либо ошибка, либо таблица с транзакциями
    resHtml = await response.text();
    //console.log('Получен список транзакций');
  }
  else {
    // Сервер вернул ошибку или не ответил
    resHtml = '<h4>Не удалось получить ответ от сервера</h4>';
  }

  // Удалим результат пред. запроса, помещенный ранее в статический блок.
  // Лучше это делать непосредственно перед выводом ответа, так не будет мигать
  divResHtml = document.getElementById("divResHtml");
  if (divResHtml) {
    divResHtml.remove();
  }

  // Поместим таблицу транзакций в статический блок, размещенный ниже формы.
  // Добавим div для очистки результата при последующих запросах.
  // Id блока нужно выбрать отличным от id таблицы
  resHtml = '<div id="divResHtml">' + resHtml + '</div>';
  jsTxList.insertAdjacentHTML('afterbegin', resHtml);
};  // Обработка нажатия кнопки формы


/***** Обработка клика на строку таблицы транзакций *****/
jsTxList.onclick = function(event) {
  // event.target - элемент, на котором произошло событие
  // Получим ближайшего предка (элемент tr), включающий поле, на котором был клик
  let tableRow = event.target.closest('tr');
  // Был ли клик внутри строки?
  if (!tableRow) return;
  // Это строка нашей таблицы?
  if (!jsTxList.contains(tableRow)) return;
  // В теге <tr> д.б. атрибут id с хешем транзакции.
  // Если клик на строке заголовка таблицы, у неё нет id
  if (!tableRow.id) return;
  /*
  console.log("target = " + event.target.tagName +  // здесь д.б. TD (поле таблицы)
        "; row = " + tableRow.tagName +  // здесь д.б. TR (строка таблицы)
        ", id: " + tableRow.id +         // здесь д.б. хеш транзакции
        "; this = " + this.tagName +     // здесь д.б. div
        ", id: " + this.id               // с id txList
        );
  */
  // Всплывающее окно обычно открывается в новой вкладке.
  // Если задать заголовок, то при просмотре другой транзакции,
  // окно будет заменено новыми данными, иначе будет открыта новая вкладка.
  const txWindow = window.open(`<?=$arrConst['api_root']?>/txGet.php?txHash=${tableRow.id}`
    //, 'Детали транзакции'  // Заголовок окна (м.б. заменён вызываемым скриптом)
  );
  // Установим фокус на новое окно (может не работать в определённых случаях)
  txWindow.focus();
}

</script>

<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>