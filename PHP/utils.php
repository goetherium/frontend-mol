<?php
namespace BlockChain\Utils;
use BlockChain\Logger as Logger;

/* 23.12.2020
 * Библиотека утилит
 */

/*************** Функции работы с пользователями ************************/
// Проверяет принадлежность текущего пользователя к группе админов
function IsAdmin() {
  global $USER;
  $userId = intval($USER->GetID());
  if ($USER->IsAdmin()) {
    Logger\log_debug("utils.php.IsAdmin. Пользователь - админ, его id: $userId");
    return true;
  }
  else {
    Logger\log_debug("utils.php.IsAdmin. Пользователь - НЕ админ, его id: $userId");
    return false;
  }
}

/* Возвращает имя и фамилию пользователя.
 * Если id пользователя не задан, то текущего
 */
function getUserName(int $userId = null) {
  // Получаем id текущего пользователя, если не задан на входе
  if (empty($userId)) {
    global $USER;
    $userId = intval($USER->GetID());
  }
  if (empty($userId)) {
    throw new Exception('Ошибка получения id пользователя из БД сайта');
  }

  // Получение данных пользователя из БД в объект CDBResult (буфер Bitrix)
  $rsUser = \CUser::GetByID($userId);
  // Получение пользователя из буфера Bitrix в массив
  $arUser = $rsUser->Fetch();
  if (empty($arUser)) {
    throw new Exception('Ошибка получения данных пользователя из БД сайта по id');
  }

  $userName = $arUser['LAST_NAME'].' '.$arUser['NAME'].' '.$arUser['SECOND_NAME'];
  Logger\log_debug('utils.php.getUserName. Получено ФИО пользователя: '.$userName);
  return $userName;
}
    

// Возвращает адрес в блокчейне владельца услуг, 
// на который пользователи переводят транзакции
function getOwnerAddress() {
  global $arrSettings;
  if (empty($arrSettings['owner_address'])) {
    throw new Exception('Ошибка считывания из настроек адреса аккаунта владельца услуг');
  }
  return $arrSettings['owner_address'];
}

/*************** Функции поддержки блокчейна ************************/
// Возвращает объект с ошибкой в Json-формате
function getJsonError($pErrCode, $pErrMsg) {
  return "{\"error\":{\"code\":$pErrCode,\"message\":\"$pErrMsg\"}}";
}

/* Переводит строку, содержащую UTC дату-время в дату с текущей временной зоной
 * Используется для преобразования дат, полученных из блокчейна в местное время.
 * Блокчейн выдаёт даты в JSON-формате, который содержит время по UTC и
 * которое нужно привести к местному времени.
 * Пример входных данных - строка 2020-12-23T01:29:14.000Z
 * где:
 *   символ "T" используется в качестве разделителя;
 *   символ "Z" обозначает время в UTC (zero-смещение).
 */
function ConvertUTCStr2LocalDate($pUTCDateTime)
{
  // Преобразуем строку в дату.
  // Символ \ заставляет использовать глобальные функции
  $dt = new \DateTime($pUTCDateTime);
  // date_default_timezone_get для Москвы дает Europe/Moscow
  $tz = new \DateTimeZone(date_default_timezone_get());
  // Устанавливаем локальную зону
  $dt->setTimezone($tz);
  // echo $dt->format('Y-m-d H:i:s');  
  return $dt;
}

/* Обратная функция - для преобразования локальной даты в UTC время,
 * и перевода в текстовый формат, ожидаемый JavaScrypt,
 * перед отправкой запроса в блокчейн.
 * Если исп. json_encode, то json_encode($date) от даты 27.12.2020 23:59:59 выдаст
 * {"date":"2020-12-27 23:59:59.000000","timezone_type":3,"timezone":"UTC"},
 * а нужно 2020-12-27T23:59:59.000Z
 */
function ConvertLocalDate2UTCStr($pLocalDateTime)
{
  // символ "T" используется в качестве разделителя;
  // символ "Z" обозначает время в UTC (zero-смещение).
  // date_default_timezone_get для Москвы дает Europe/Moscow
  $tz = new \DateTimeZone('UTC');
  $dt = $pLocalDateTime;
  // Устанавливаем зону UTC
  $dt->setTimezone($tz);
  $dtStr = $dt->format('Y-m-d').'T'.$dt->format('H:i:s').'.000Z';
  return $dtStr;
}

?>