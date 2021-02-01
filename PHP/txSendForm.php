<?php
/* 26.12.2020
 * Форма ввода данных транзакции админом/клиентом.
 * Если вызвана админом, на входе д.б. задан GET-параметр userId.
 * Результаты проведения транзакции отображаются в модальном окне JavaScript
 */

// Инициализация приложения, загрузка модулей и заголовка страницы
require_once './initApp.php';

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

// Подключаем стиль модального окна
use Bitrix\Main\Page\Asset;
Asset::getInstance()->addCss("{$arrConst['api_root']}/css/txSendForm.css");
Logger\log_debug('txSendForm.php. Загружены стили модального окна');

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
Logger\log_debug('txSendForm.php. Входные параметры проверены');

// Выводим ФИО пользователя
if ($isAdmin) {
  echo '<h3>Клиент: '.Utils\getUserName($userId).'</h3><br>';
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
        ">Создать клиенту кошелёк</a>";
  }
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
  exit;
}

if (isset($firstAccountUser['balance'])) {
  echo 'Баланс кошелька: '.round(floatval($firstAccountUser['balance']),2).' '.
       $arrConst['blockchain_value_name_genitive'].'<br>';
}
if (isset($firstAccountUser['createdDate'])) {
  echo 'Дата создания кошелька: '.
        Utils\ConvertUTCStr2LocalDate($firstAccountUser['createdDate'])->format('d.m.Y').'<br>';
}
echo '<br><br>';

// Определим заголовок формы
if ($isAdmin) {
  $formLegend = 'Отправка транзакции клиенту';
}
else {
  $formLegend = 'Отправка транзакции владельцу услуг';
}

?>
<!-- Анимированный gif ожидания проведения транзакции.
     Взят с www.ajaxload.info
-->
<div>
<img id="sendingTx" src="./img/sendingTx.gif" class="bc-txsend__animated-gif">
</div>

<!-- Форма ввода параметров транзакции -->
<form id="txSendForm">
  <legend><?=$formLegend?></legend>
  <p>
    <input type="hidden" id="userId" name="userId" value="<?=$userId?>">
    <label for="txValue">Сумма <?=$arrConst['blockchain_value_name_genitive']?></label>
    <input type="number"
           id="txValue" 
           name="txValue"
           autofocus="true" 
           placeholder="10.53" 
           min="0"
           step="0.01"
           required="true"
           value=""
     >
  </p>
<p><input id="txSendButton" type="submit" value="Провести транзакцию в блокчейне"></p>
</form>

<p>Проведение транзакции может занять до 15 секунд</p><br>

<!--***** Результаты проведения транзакции отображаем в модальном окне *****-->
<!-- Фон модального окна -->
<div id="modalWindow" class="bc-txsend__modal--bg">
  <!-- Модальное окно, его классы задают вид некоторых элементов -->
  <div class="bc-txsend__modal--win">
    <!-- 
      Кнопка "Закрыть" справа свехру модального окна.
      Чтобы не перегружать страницу, перейти к фиктивному блоку noWindow или погасить переход в JS
    <a id="txTopButton" href="#noWindow" title="Закрыть" class="bc-txsend__modal--topbutton">X</a> -->
    <p id="txSendResult" align="center">Здесь будет результат транзакции</p>
    <div>
    <input id="modalBottomButton" type="button" value="Закрыть" class="bc-txsend__modal--bottombutton">
    </div>
  </div>
</div>
<!--***** Конец модального окна *****-->

<script>
// Форма параметров транзакций
const jsTxSendForm = document.getElementById("txSendForm");
// Модальное окно с результатом транзакции
const jsModalWindow = document.getElementById("modalWindow");
// Элемент модального окна с результатом проведения транзакции
const jsTxSendResult = document.getElementById("txSendResult");
// Кнопка закрытия модального окна
const jsModalBottomButton = document.getElementById("modalBottomButton");
// Кнопка отправки транзакции
const jsTxSendButton = document.getElementById("txSendButton");
// Анимированный gif ожидания проведения транзакции
const jsSendingTx = document.getElementById("sendingTx");


/***** Проведение транзакции при нажатии кнопки в форме *****/
jsTxSendForm.onsubmit = async (event) => {
  // Браузер не будет отправлять форму, мы сами отправим запрос
  event.preventDefault();
  // Запрещаем кнопку отправки транзакции
  jsTxSendButton.disabled = true;
  // Показываем анимированный gif
  jsSendingTx.style.display = 'block'; 
  //jsSendingTx.style.position = 'relative';
  //jsSendingTx.style.margin = 'auto';
  //setInterval( () => {jsSendingTx.style.display = 'none'}, 3000);

  const response = await fetch(`<?=$arrConst['api_root']?>/txSend.php`, {
    method: 'POST',
    body: new FormData(jsTxSendForm),  // FormData получит все данные формы
  });
  // Активируем кнопку отправки транзакции
  jsTxSendButton.disabled = false;
  // Скрываем анимированный gif
  jsSendingTx.style.display = 'none'; 

  let txSendRes;
  // Если код ответа 200-299 ...
  if (response.ok) {
    // Получим готовый HTML в виде текста - либо ошибка, либо таблица с транзакциями
    txSendRes = await response.text();
    //console.log('Результат проведения транзакции: '+ txSendRes);
  }
  else {
    // Сервер вернул ошибку или не ответил
    txSendRes = 'Не удалось получить ответ от сервера';
  }

  // Поместим результат проведения транзакции в модальное окно.
  jsTxSendResult.innerHTML = txSendRes;
  // Активируем модальное окно
  jsModalWindow.style.display = 'block';
  // Разрешаем обработку модальным окном событий мыши.
  // Когда окно скрыто, события запрещены.
  jsModalWindow.style.pointerEvents = 'auto';
};  // Обработка нажатия кнопки формы

// Обработчик кнопки закрытия модального окна
// Скрывает окно и запрещает в нём обработку мыши.
jsModalBottomButton.onclick = function() {
  jsModalWindow.style.display = 'none';
  jsModalWindow.style.pointerEvents = 'none';
}

</script>


<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>