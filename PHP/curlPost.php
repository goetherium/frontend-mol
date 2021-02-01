<?php
namespace BlockChain\Curl;
/* 10.11.2020
 * Библиотека выполняет HTTP-вызов к удаленному серверу
 * методом POST
 */

/* Возвращает массив опций POST-запроса для cURL
 * Входные параметры:
 *   $options - массив, содержащий доп. опции cURL, 
 *   которые перезапишут опции по умолчанию.
 *   В этот параметр следует передать как минимум
 *   URL сервера и данные post-запроса
 */
function curl_get_options(array $options = array())
{
  // список параметров см. https://www.php.net/manual/ru/function.curl-setopt.php
  $defaults = array(
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS, // версия протокола м.б. опущена до HTTP 1.1
    CURLOPT_HTTPHEADER	   => array('Content-type: application/json'),
    CURLOPT_POST           => true,         // отправка post-запроса

    CURLOPT_SSL_VERIFYHOST => 2,            // 2 - проверка существования общего имени и также его совпадения с указанным хостом
    CURLOPT_SSL_VERIFYPEER => true,         // проверка сертификата узла сети
    //CURLOPT_SSL_ENABLE_ALPN =>true,       // включить ALPN в SSL handshake
    //CURLOPT_CERTINFO       => true,       // вывод информации о сертификате сервера

    CURLOPT_RETURNTRANSFER => true,         // при успешном завершении будет возвращен результат, а при неудаче - FALSE
    CURLOPT_HEADER         => false,        // возвращать ли заголовки, или только данные из ответа сервера
    CURLOPT_FAILONERROR    => false,        // подробный отчет при неудаче, если полученный HTTP-код больше или равен 400

    CURLOPT_USERAGENT      => 'myownlife',  // who am i
    CURLOPT_FOLLOWLOCATION => true,         // follow redirects
    CURLOPT_AUTOREFERER    => true,         // set referer on redirect
    CURLOPT_CONNECTTIMEOUT => 60,           // timeout on connect
    CURLOPT_TIMEOUT        => 60,           // timeout on response
    CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects

    CURLOPT_VERBOSE        => false,         // вывод дополнительной информации
  );
  
  /* Ключи cRUL - числовые, поэтому вместо array_merge нужно использовать +.
   * Тогда ключи из первого массива будут сохранены. 
   * Если ключ массива существует в обоих массивах, то будет использован 
   * элемент из первого массива, а соответствующий элемент из второго 
   * массива будет проигнорирован
   */
  $options = $options + $defaults;  // порядок важен
  //\BlockChain\Logger\log_debug("curl_get_options. Параметры для вызова curl_exec:\n"
  //        .print_r($options, true));
  return $options;
}

/* Выполнение POST-запроса с открытием и закрытием cURL-сессии
 * Входные параметры:
 *   $url - URL сервера
 *   $curl_data - данные post-запроса
 * Возвращает массив, например
    [errno] => 0
    [errmsg] => 
    [content] => {"jsonrpc":"2.0","id":1,"error":{"code":-1001,"message":"Пользователь с данным логином уже существует"}}
 */
function curl_post(array $options = array())
{
  // ch - cURL handler
  // URL устанавливается не в init, а отдельно массивом опций
  $ch = curl_init();

  curl_setopt_array($ch, curl_get_options($options));
  /* Поскольку установлена опция CURLOPT_RETURNTRANSFER => true, 
   * при успешном завершении будет возвращен результат, а при неудаче - FALSE.
   * При этом коды состояния ответа, указывающие на ошибки 
   * (например, 404 Not found), не рассматриваются как неудача.
   * Если ответ сервера пустой, то curl_exec вернёт true, а не пустую строку.
   */
  $res_array = array();
  $res_array['content'] = curl_exec($ch);
  $res_array['errno']   = curl_errno($ch);
  $res_array['errmsg']  = curl_error($ch) ;

  curl_close($ch);

  return $res_array;
}

/****** Функции открытия, выполнения и закрытия cURL-сессии *******/
/* Открытие cURL-сессии
 * Возвращает ссылку на обработчик запросов
 */
function curl_my_init()
{
  return curl_init();
}

/* Выполнение POST-запроса без открытия и закрытия cURL-сессии
 * Входные параметры:
 *   $ch - cURL handler - ссылка на обработчик запроса, полученный curl_init
 *   $url - URL сервера
 *   $curl_data - данные post-запроса
 * Возвращает массив, например
    [errno] => 0
    [errmsg] => 
    [content] => {"jsonrpc":"2.0","id":1,"error":{"code":-1001,"message":"Пользователь с данным логином уже существует"}}
 */
function curl_my_exec($ch, array $options = array())
{
  curl_setopt_array($ch, curl_get_options($options));

  $res_array = array();
  $res_array['content'] = curl_exec($ch);
  if (curl_errno($ch)) {
    $res_array['errno']  = curl_errno($ch);
    $res_array['errmsg'] = curl_error($ch) ;
  }
  return $res_array;
}

/* Закрытие cURL-сессии
 * Входные параметры:
 *   $ch - cURL handler - ссылка на обработчик запроса, полученный curl_init
 */
function curl_my_close($ch)
{
  curl_close($ch);
}


/* Ниже пример HTTP вызова с использованием file_get_contents.
 * Тест показал, что эта функция не поддерживает HTTP/2.
 * Если в параметре protocol_version указать 2.0, запрос передается,
 * но nginx (настроенный на работу с HTTP/2) выдает ошибку:
 * HTTP/1.1 505 HTTP Version Not Supported
 * PHP в заголовке указывает HTTP/2, но видимо реально работает 
 * по HTTP 1.1 и сервер не понимает это.
 *
 * В статье https://wiki.php.net/ideas/php6#http2_support разъяснение:
 * Mainly the HTTP part of PHP's stream implementation requires changes. 
 * However it is not as easy as it may sound. 
 * I would not recommend to implement our own HTTP2 support 
 * but to use existing and well tested library. 
 * nghttp2 is one of the most popular, complete and widely used HTTP2 library
 * (used by CURL).
 *
 * Т.о. file_get_contents нельзя использовать с протоколом HTTP/2.
 * 
 * Для обоих протоколов HTTP и HTTPS в контексте нужно указать 'http' 
 * При использовании протокола HTTP 1.1, если сервер использует keep-alive,
 * функция будет медленно работать. В таком случае нужно использовать
 * заголовок Connection: close
$context_options = array('http' =>
  array(
    'method' => 'POST',
    'protocol_version' => 1.1,
    'header' => ['Content-type: application/json',
                 'Connection: close'
                ],
    'content' => json_encode($post_data)
    )
);

$context = stream_context_create($context_options);
$host = 'https://my.alladin.host';
$result = file_get_contents($host, false, $context);
*/

/* Для работы с веб-сервером по протоколу HTTP/2 используем curl 
 * версии 7.38.0 и выше. 
 * Библиотека libcurl, используемая утилитой curl, должна быть собрана
 * с поддержкой модуля nghttp2, проверить это легко:
 * curl -V
 * Результат:
 * curl 7.58.0 (x86_64-pc-linux-gnu) libcurl/7.58.0 OpenSSL/1.1.1 
 * zlib/1.2.11 libidn2/2.0.4 libpsl/0.19.1 (+libidn2/2.0.4) 
 * nghttp2/1.30.0 librtmp/2.3
 * Проверить факт использования HTTP/2 можно в результате вызова curl_exec:
 * первая строка ответа сервера будет содержать HTTP/2 200
 */

/* Пример post-запроса через cURL
function curl_do_post($url, array $post = NULL, array $options = array())
{
    $defaults = array(
        CURLOPT_POST => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_URL => $url,
        CURLOPT_FRESH_CONNECT => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FORBID_REUSE => 1,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_POSTFIELDS => http_build_query($post)
    );

    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch))
    {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return $result;
}
*/

/* Пример get-запроса через cURL
function curl_do_get($url, array $get = NULL, array $options = array())
{   
    $defaults = array(
        CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get),
        CURLOPT_HEADER => 0,
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_TIMEOUT => 4
    );
   
    $ch = curl_init();
    curl_setopt_array($ch, ($options + $defaults));
    if( ! $result = curl_exec($ch))
    {
        trigger_error(curl_error($ch));
    }
    curl_close($ch);
    return $result;
}
*/



?>