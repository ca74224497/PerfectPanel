<?php
/**
 * Входная точка приложения.
 */

// Выключение отображения ошибок.
error_reporting(error_level: 0);
ini_set(option: 'display_errors', value: 0);

// Включение поддержки оберток URL.
ini_set(option: 'allow_url_fopen', value: 1);

// Кодировка + тип отдаваемного документа (JSON).
header(header: 'Content-type: application/json; charset=utf-8');

// Импорт классов приложения
// (для автоматической загрузки можно использотвать "spl_autoload_register" или Composer + PSR-4).
require_once 'App.php';
require_once 'Components/Response.php';
require_once 'Components/HttpCodes.php';
require_once 'Components/Auth.php';
require_once 'Components/CurrencyApi.php';
require_once 'Components/RemoteQuery.php';

use App\Components\HttpCodes;
use App\RequestHandler;
use App\Components\Response;

try {
    // Запуск обработчика запросов.
    (new RequestHandler())->init();

} catch (Throwable $t) {
    $message = $t->getMessage();
    $code = $t->getCode() ? $t->getCode() : HttpCodes::HTTP_BAD_REQUEST;
    error_log(message: $message, message_type: 0);
    Response::send(payload: $message, type: 'error', httpCode: $code);
}