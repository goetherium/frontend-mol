<?php

use BlockChain\Curl as Curl;
use BlockChain\Logger as Logger;

/* 24.12.2020
 * Класс для работы с блокчейном
 */

class CBlockChain
{
  // Ссылка на CURL-обработчик запросов
  private $curlHandler;
  // Массив опций CURL
  private $arrCurlOptions;
  // Массив идентификационных данных для запросов в блокчейн
  private $arrHmac;
  // Массив параметров запроса
  private $arrPostData;
  
  function __construct(array $bcParams = null) {
    global $arrConst;
    // Получаем логин и пароль
    $this->arrHmac = $this->getHmac($bcParams['userId']);
    // Ссылка на объект CURL
    $this->curlHandler = Curl\curl_my_init();
    // Постоянные опции запросов
    $this->arrCurlOptions = array(CURLOPT_URL => $arrConst['jsonrpc_url']);
    // Постоянные опции запроса
    $this->arrPostData = array('jsonrpc' => $arrConst['jsonrpc_version'],
                               'id'      => 1);
  }
  // Вызывается при завершении работы скрипта или удалении объекта
  function __destruct() {
    // Закрываем соединение с блокчейном
    Curl\curl_my_close($this->curlHandler);
  }


  /* Получение логина и hmac пароля.
   * Логин передается в блокчейн в открытом виде, чтобы можно было
   * потом получить историю транзакций с указанием логинов вместо адресов.
   * Хэш id пользователя, его логина и даты регистрации передается 
   * в виде пароля в блокчейн.
   * BX_USER_ID не используем, вроде бы он может меняться.
   * Входной параметр - id пользователя следует задавать только если 
   * операцию с блокчейном выполняет админ на этим пользователем.
   * Если id пользователя не задан, он будет получен из переменной $USER Битрикса.
   */
  private function getHmac(int $userId = null)
  {
    // Массив настроек
    global $arrSettings;
    /* Переменная USER определяется при загрузке /bitrix/header.php
     * в вызывающем модуле.
     * Работа с пользователем см. /bitrix/modules/main/classes/general/user.php
     * CUser - класс для работы с пользователями.
     * При загрузке каждой страницы автоматически создаётся объект этого класса $USER.
     */
   
    // Получаем id пользователя, если не задан на входе
    if (empty($userId)) {
      global $USER;
      $userId = intval($USER->GetID());
    }
    if (empty($userId)) {
      throw new Exception('Ошибка получения id пользователя из БД сайта');
    }

    // Получение данных пользователя из БД в объект CDBResult (буфер Bitrix)
    $rsUser = CUser::GetByID($userId);
    // Получение пользователя из буфера Bitrix в массив
    $arUser = $rsUser->Fetch();
    if (empty($arUser)) {
      throw new Exception('Ошибка получения данных пользователя из БД сайта по id');
    }

    // В полученном массиве используем неизменяемые поля
    $userData = 'скрыто для github';
    Logger\log_debug('CBlockchain.php.getHmacData. Id пользователя: '.$arUser['ID'].
                     ', логин: '.$arUser['LOGIN'].
                     ', данные для хэширования: '.$userData);
  
    // считываем секретное слово из настроек для подмешивания в hmac
    $hmacSecret = $arrSettings['hmac_secret'];
    if (!$hmacSecret) {
      throw new Exception('Ошибка получения настроек');
    }
    Logger\log_debug('CBlockchain.php.getHmacData. Считан секрет для hmac').

    $this->arrHmac['login'] = $arUser['LOGIN'];
    $this->arrHmac['pwd']   = hash_hmac('sha3-256', $userData, $hmacSecret);

    Logger\log_debug('CBlockchain.php.getHmacData. логин: '.$this->arrHmac['login'].
                     ', hmac пароля: '.$this->arrHmac['pwd']);
    return $this->arrHmac;
  }  // getHmac




  /* ****************** Создание пользователя ******************** */
  public function userAdd()
  {
    global $arrConst;

    /* Пример строки данных POST-запроса
     * {"jsonrpc":"2.0","id":1,"method":"userAdd",
     *  "params":{"realm":"mol","userLogin":"Account9"}}
     */
    $this->arrPostData['method'] = 'userAdd';
    $this->arrPostData['params'] = array('realm' => $arrConst['jsonrpc_realm'],
                                         'userLogin' => $this->arrHmac['login']);
    $curlData = json_encode($this->arrPostData);
    Logger\log_debug('CBlockchain.php.userAdd. Запрос создания пользователя: '.$curlData);

    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.userAdd. Ответ сервера: '.$arrResp['content']);

    /* Успешный результат:
     * {"jsonrpc":"2.0","id":5,"result":{"createdDate":"2020-12-25T12:20:10.000Z"}}
     * Ошибка:
     * {"jsonrpc":"2.0","id":1,"error":{"code":-2001,"message":"Пользователь с данным логином уже существует"}}
     */
    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!== -2001) 
    {
      throw new Exception('CBlockChain.php.userAdd. Ошибка создания пользователя. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', ответ блокчейна: '.$arrResp['content']);
    }

    if ($respContent['error']['code']== -2001) {
      Logger\log_debug('CBlockchain.php.userAss. Пользователь уже существует');
      return null;
    }
    else {
      return $respContent['result'];  // данные пользователя
    }
  }  // userAdd


  /*********************** Получение пользователя из блокчейна ***********************/
  public function userGet() 
  {
    global $arrConst;

    /* Пример запроса
     * {"jsonrpc":"2.0","method":"userGet","params":
     * {"realm":"mol","userLogin":"Account9"},"id":5}
     */
    $this->arrPostData['method'] = 'userGet';
    $this->arrPostData['params'] = array('realm' => $arrConst['jsonrpc_realm'],
                                         'userLogin' => $this->arrHmac['login']);
    $curlData = json_encode($this->arrPostData);
    Logger\log_debug('CBlockchain.php.userGet. Запрос пользователя: '.$curlData);

    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.userGet. Ответ сервера: '.$arrResp['content']);

    /* Упешный результат
     * {"jsonrpc":"2.0","id":5,"result":{"createdDate":"2020-06-08T03:23:30.000Z"}}
     * Пользователь не найден
     * {"jsonrpc":"2.0","id":5,"error":{"code":-2002,"message":"Логин user1 не найден"}}
     */
    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!= -2002)
    {
      throw new Exception('CBlockchain.php.UserGet.  Ошибка запроса пользователя. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', ответ блокчейна: '.$arrResp['content']);
    }

    if ($respContent['error']['code']== -2002) {
      Logger\log_debug('CBlockchain.php.userGet. Пользователь не найден');
      return null;
    }
    else {
      return $respContent['result'];  // данные пользователя
    }
  }  // UserGet



  /* *************** Создание аккаунта по умолчанию ********************* */
  public function accountAdd()
  {
    global $arrConst;

    /* Пример запроса
     * {"jsonrpc":"2.0","id":5,"method":"accountAdd",
     *  "params":{"realm":"mol","userLogin":"Account9","accountName":"main","accountPassword":"test"}}
     *
     * Кириллица в названии аккаунта будет закодирована json_encode по UTF8
     * и в кодированном виде сохранена в блокчейне.
     * Например, аккаунт с названием "Основной аккаунт" будет закодирован:
     * \u041e\u0441\u043d\u043e\u0432\u043d\u043e\u0439 \u0430\u043a\u043a\u0430\u0443\u043d\u0442
     * Декодирование json_decode восстановит кириллицу.
     */
    $this->arrPostData['method'] = 'accountAdd';
    $this->arrPostData['params'] = array('realm' => $arrConst['jsonrpc_realm'],
                                         'userLogin' => $this->arrHmac['login'],
                                         'accountName' => $arrConst['jsonrpc_default_account_name'],
                                         'accountPassword' => $this->arrHmac['pwd'],
                                        );
    $curlData = json_encode($this->arrPostData);
    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    Logger\log_debug('CBlockchain.php.accountAdd. Запрос создания аккаунта: '.$curlData);
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.accountAdd. Ответ сервера: '.$arrResp['content']);

    /* При успехе
     * {"jsonrpc":"2.0","id":5,"result":
     * {"createdDate":"2020-12-25T12:21:17.000Z","address":"0x1b414103Bb814a1f6633A700331ab2a4C54c26c5"}}
     * Ошибка
     * {"jsonrpc":"2.0","id":5,"error":{"code":-2001,"message":"Аккаунт уже существует"}}
     */
    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!= -2001) 
    {
      throw new Exception('CBlockchain.php.AccountAdd. Ошибка создания аккаунта в блокчейне. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', код ошибки: '.$respContent['error']['code']
                         .', текст ошибки: '.$respContent['error']['message']);
    }

    if ($respContent['error']['code']== -2001) {
      Logger\log_debug('CBlockchain.php.AccountGet. Аккаунт уже существует');
      return null;
    }
    else {
      return $respContent['result'];  // данные аккаунта
    }
  }


  /************ Получение аккаунта из блокчейна **********/
  public function accountGet() 
  {
    global $arrConst;

    /* Пример запроса
     * {"jsonrpc":"2.0", "method":"accountGetList", 
     * "params":{"realm":"mol","userLogin":"Account1"}, "id":5}
     */
    $this->arrPostData['method'] = 'accountGetList';
    $this->arrPostData['params'] = array('realm' => $arrConst['jsonrpc_realm'],
                                         'userLogin' => $this->arrHmac['login']);
    $curlData = json_encode($this->arrPostData);
    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    Logger\log_debug('CBlockchain.php.accountGet. Запрос списка аккаунтов: '.$curlData);
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.accountGet. Ответ сервера: '.$arrResp['content']);

    /* При успехе
     * {"jsonrpc":"2.0","id":5,
     * "result":[{"accountAddress":"0xBf06F62a924a4ee8866b3a33fA1b10818d5f24F8",
     * "accountName":"Основной аккаунт","createdDate":"2020-11-13T06:30:22.000Z",
     * "balance":"0"}]}
     * Аккаунт не найден
     * {"jsonrpc":"2.0","id":1,"error":{"code":-2002,"message":"Учетных записей для логина 7b4e0de87e6cd40965dcde93ddff9e12c73683f9254e0737fc6ede07bbb92a3c не найдено"}}
     */
    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!= -2002) {
      throw new Exception('CBlockchain.php.AccountGet. Ошибка получения списка аккаунтов. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', ответ блокчейна: '.$arrResp['content']);
    }

    if ($respContent['error']['code']== -2002) {
      Logger\log_debug('CBlockchain.php.AccountGet. Аккаунт не найден');
      return null;
    }
    else {
      return $respContent['result'];  // список аккаунтов
    }
  }  // AccountGet


  /************* Отправка транзакции **************/
  public function txSend(array $inParams)
  {
    /* Пример запроса
     * {"jsonrpc":"2.0", "method":"txSend", "id":5, "params":{
     * "accountAddress":"0xCfEDa4919c8971f0581B12D76d5d85F3674651f5",
     * "destinationAddress":"0x4394Fba7da2716dD3181532A59aF2Dd0BcFD17ee",
     * "etherValue":"0.07",
     * "accountPassword":"123"}}
     */
    $etherValue = floatval($inParams['txValue']);
    $this->arrPostData['method'] = 'txSend';
    // пароля берем из учётки, которая вызвала данный метод отправки Тх
    // дроби нужно передавать в кавычках, иначе Geth выдаст ошибку
    // "Please pass numbers as strings or BN objects to avoid precision errors."
    $this->arrPostData['params'] = array('accountAddress'     => $inParams['accountAddress'],
                                         'destinationAddress' => $inParams['destinationAddress'],
                                         'etherValue'         => "$etherValue",
                                         'accountPassword'    => $this->arrHmac['pwd'],
    );
    $curlData = json_encode($this->arrPostData);
    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    Logger\log_debug('CBlockchain.php.txSend. Запрос отправки Тх: '.$curlData);
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.txSend. Ответ сервера: '.$arrResp['content']);

    /* Успешный результат
     * {"jsonrpc":"2.0","id":5,"result":
     * {"txHash":"0xecd35742d45b31238bb7f5a75bb341e5800170a21631a543c2b836eba669061a","blockNumber":286438}}
     * Ошибка:
     * {"jsonrpc":"2.0","id":5,"error":{"code":-1003,"message":
     * "Returned error: insufficient funds for gas * price + value"}}
     */
    $arResult = array();
    if ($arrResp['errno'] || (!$respContent) || $respContent['error'])
    {
      Logger\log_error('CBlockchain.php.txSend.  Ошибка отправки транзакции. '
                      .'Данные запроса: '.$curlData
                      .', ошибка cURL: '.$arrResp['errmsg']
                      .', ответ блокчейна: '.$arrResp['content']);
      $arResult['RESULT'] = 'ERROR';
      if (strpos($respContent['error']['message'], 'insufficient funds') >= 0) {
        $arResult['USER_MSG'] = 'Недостаточно средств для проведения транзакции';
      }
      else {
        $arResult['USER_MSG'] = 'Ошибка проведения транзакции';
      }
    }
    else {
      $arResult['RESULT'] = 'SUCCESS';
      $arResult['USER_MSG'] = 'Транзакция успешно проведена';
    }
    return $arResult;
  }  // txSend


  /****************** Получение списка транзакций ***************/
  public function txGetList(array $arrParams) 
  {
    /* Пример запроса
     * {"jsonrpc":"2.0","method":"txGetList","id":5,
     *  "params":{"direction":"Out","accountAddress":"0x6F43ce33C1E9b1Dfc638e4929cdfbF228Fc30864",
     *  "dateFrom":"2020-06-14T03:23:30+03:00","dateTo":"2020-06-16T23:59:59+03:00"}}
     */

    $this->arrPostData['method'] = 'txGetList';
    $this->arrPostData['params'] = array(
        'direction' => $arrParams['direction'],
        'accountAddress' => $arrParams['accountAddress'],
        'dateFrom' => $arrParams['dateFrom'],
        'dateTo' => $arrParams['dateTo'],
        'sortOrder' => $arrParams['sortOrder'],
    );
    $curlData = json_encode($this->arrPostData);
    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    Logger\log_debug('CBlockchain.php.txGetList. Запрос списка транзакций: '.$curlData);
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.txGetList. Ответ сервера: '.$arrResp['content']);

    /* Успешный результат
     * {"jsonrpc":"2.0","id":5,"result":[{"sourceUserLogin":"Account30",
     * "destUserLogin":"Account31",
     * "txHash":"0xa8b0ea669775ae8031089937b168a2c80ee806395d6c9f16c7f8fc4c3a144b9a",
     * "sourceAddress":"0xC4d7AdA8Dc2212a21A53cF430E9E3e789e59A234",
     * "destAddress":"0x81Bae0ceFf43237a0811E5789a538a0D9dD5bA50",
     * "txCreatedDate":"2020-12-20T01:01:44.000Z",
     * "weiValue":"520000000000000000","etherValue":"0.52"}]}
     */
    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!= -2002)
    {
      throw new Exception('CBlockchain.php.txGetList.  Ошибка получения списка транзакций. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', ответ блокчейна: '.$arrResp['content']);
    }

    if ($respContent['error']['code']== -2002) {
      Logger\log_debug('CBlockchain.php.txGetList. Транзакций не найдено');
      return null;
    }
    else {
      return $respContent['result'];  // список транзакций
    }
  }  // txGetList
  


  /************* Запрос транзакции **************/
  public function txGet(string $txHash)
  {
    /* Пример запроса
     * {"jsonrpc":"2.0","method":"txGet","id":5,
     * "params":{"txHash":"0x8adb5b0cc97401600e50ee0c055b592b389419edd31e18eb7bd1a42d1dcdcf71"}}
     */
    $this->arrPostData['method'] = 'txGet';
    $this->arrPostData['params'] = array('txHash' => $txHash);
    $curlData = json_encode($this->arrPostData);
    $this->arrCurlOptions[CURLOPT_POSTFIELDS] = $curlData;
    Logger\log_debug('CBlockchain.php.txGet. Запрос транзакции: '.$curlData);
    $arrResp = Curl\curl_my_exec($this->curlHandler, $this->arrCurlOptions);
    $respContent = json_decode($arrResp['content'], true);
    Logger\log_debug('CBlockchain.php.txGet. Получена транзакция: '.$arrResp['content']);

    if ($arrResp['errno'] || (!$respContent) ||
        $respContent['error'] && $respContent['error']['code']!== -2002
    )
    {
      throw new Exception('CBlockchain.php.txGet.  Ошибка получения транзакции. '
                         .'Данные запроса: '.$curlData
                         .', ошибка cURL: '.$arrResp['errmsg']
                         .', ответ блокчейна: '.$arrResp['content']);
    }
    
    if ($respContent['error']['code']== -2002) {
      Logger\log_error("Для хеша $txHash транзакция не найдена");
      return null;
    }
    else {
      return $respContent['result'];  // транзакция
    }
  }  // txGet
  
}  // class

?>