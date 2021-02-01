<?php
/* 09.12.2020
 * Главный скрипт работы с блокчейном.
 * Проверяет, админ ли это или обычный пользователь
 * и отображет соотв. функционал
 */

// Инициализация приложения, загрузка модулей и заголовка страницы
require_once './initApp.php';

use BlockChain\Logger as Logger;
use BlockChain\Utils as Utils;

Logger\log_debug('index.php. Начало работы скрипта');

$isAdmin = Utils\IsAdmin();

//if ($isAdmin) {

// Проверим существование админа в БД блокчейна
$bcAccountExists = false;
$bc = new CBlockchain();
if ($bc->userGet()) {
  // Основной аккаунт админа
  $firstAccount = $bc->accountGet()[0];
  if (isset($firstAccount)) {
    $bcAccountExists = true;
  }
}

if (!$bcAccountExists) {
  echo 'Ваш кошелёк ещё не создан<br><br>';
  if ($isAdmin) {
    echo "<a class='button' href='{$arrConst['api_root']}/userAdd.php'>Создать кошелёк администратора</a>";
  }
  else {
    echo "<a class='button' href='{$arrConst['api_root']}/userAdd.php'>Создать кошелёк</a>";
  }
  require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
  exit;
}

if ($isAdmin) {
  // Этот параметр будет передан из формы поиска при повторной загрузке страницы
  $userNameFilter = htmlentities($_POST['userNameFilter'], ENT_QUOTES);
  $userNameFilter = filter_var(
      $userNameFilter, FILTER_SANITIZE_SPECIAL_CHARS,
      array('flags' => FILTER_FLAG_STRIP_LOW |     // удаляет символы с кодами ниже 32
                      FILTER_FLAG_STRIP_BACKTICK  // удаляет символ обратной кавычки `
                      //FILTER_FLAG_ENCODE_HIGH удалит русские символы
          )
  );
  $userNameFilter = str_replace(':', '', $userNameFilter);

  echo 'Вы вошли как администратор.<br>';
  echo 'Вы можете найти клиента по части фамилии или имени (регистр не важен) ';
  echo 'для пополнения его кошелька или просмотра транзакций.<br><br><br>';

  // Форма поиска клиентов
  echo '<form action="" method="post">';
  echo '  <legend>Поиск клиентов</legend>';
  echo '  <p>';
  echo '    <label for="fio">Имя или фамилия</label>';
  echo '    <input type="search" ';
  echo '           id="fio"';
  echo '           name="userNameFilter"';
  echo '           autofocus="true"';
  echo '           minlength="3"';
  echo '           placeholder="Иванов"';
  echo '           required="true"';
  echo '           value="'.$userNameFilter.'"';
  echo '     >';
  echo '  </p>';
  echo '<p><input type="submit" value="Найти"></p>';
  echo '</form>';

  // Вывод списка найденных пользователей.
  // Поскольку данный скрипт вызывается из index.php, то 
  // стандартной постраничной навигацией класса CDBResult (NavStart, NavPrint)
  // воспользоваться не получится - ссылки будут вести на index.php
  if (isset($userNameFilter)) {
    $maxRecordCount = 100;  // макс. кол-во выбираемых пользователей

    /* Получение списка пользователей из БД сайта
    * https://dev.1c-bitrix.ru/api_help/main/reference/cuser/getlist.php
    * В фильтре можно использовать условия
    * https://dev.1c-bitrix.ru/api_help/main/general/filter.php
    */
    $filter = array(
        'ACTIVE' => 'Y',  // только активные
        'NAME' => $userNameFilter,  // поиск по имени и фамилии (ведется без учета регистра)
    );
    $arrParams = array('FIELDS' => 'id, login, last_name, second_name, name, date_register', // список выбираемых полей
                      'NAV_PARAMS' => array('nTopCount' => $maxRecordCount), // ограничение кол-ва строк                   
                  );
    // Выбираем пользователей
    $rsUsers = \CUser::GetList(
        $by = 'last_name',  // сортировка по полю
        $order = 'asc',     // варианты asc/desc
        $filter,
        $arrParams
    ); 
    if (intval($rsUsers->SelectedRowsCount()) == 0) {
      echo 'Пользователи не найдены<br>';
    }
    else {
      if (intval($rsUsers->SelectedRowsCount()) == $maxRecordCount) {
        echo "Показаны первые $maxRecordCount записей, уточните критерий поиска.<br>";
      }
      // стиль из /bitrix/css/main/bootstrap.css
      echo '<br>';
      echo '<table class="table">';
      echo '<caption>Список клиентов</caption>';
      echo '<tr>';
      echo '<th>Логин</th>';
      echo '<th>ФИО</th>';
      echo '<th>Дата регистрации</th>';
      echo '<th>Список транзакций</th>';
      echo '<th>Пополнить кошелёк</th>';
      echo '</tr>';
      // выбираем данные из буфера, f - префикс полей в глобальном массиве
      while ($arUser = $rsUsers->GetNext()) {
        echo '<tr>';
        echo "<td>{$arUser['LOGIN']}</td>";
        echo "<td>{$arUser['LAST_NAME']} {$arUser['NAME']} {$arUser['SECOND_NAME']}</td>";
        echo "<td>{$arUser['DATE_REGISTER']}</td>";
        echo "<td><a target='_blank' href='{$arrConst['api_root']}/txQueryForm.php?userId={$arUser['ID']}'".
            ">Посмотреть</a></td>";
        echo "<td><a target='_blank' href='{$arrConst['api_root']}/txSendForm.php?userId={$arUser['ID']}'".
            ">Пополнить</a></td>";
        echo '</tr>';
      }
      echo '</table>';
      Logger\log_debug('indexAdmin.php. Список пользователей выведен на страницу');
    }
  }
}  // это админ
else {
  if (isset($firstAccount['createdDate'])) {
    echo 'Дата создания кошелька: '.
          Utils\ConvertUTCStr2LocalDate($firstAccount['createdDate'])->format('d.m.Y').'<br>';
  }

  if (isset($firstAccount['balance'])) {
    echo 'Баланс кошелька: '.round(floatval($firstAccount['balance']),2).' '.
        $arrConst['blockchain_value_name_genitive'];
    if (floatval($firstAccount['balance']) >= 0) {
      echo '<br>';
      echo '<br>';
      echo "<a target='_blank' href='{$arrConst['api_root']}/txSendForm.php'>Отправить транзакцию</a>";
    }
  }
  echo '<br>';
  echo '<br>';
  echo "<a target='_blank' href='{$arrConst['api_root']}/txQueryForm.php'>Список транзакций</a>";
  echo '<br>';
}


require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>