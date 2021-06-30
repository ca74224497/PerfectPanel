<?php
namespace App;

use App\Components\AuthContext;
use App\Components\CurrencyApi;
use App\Components\CurrencyApiCommission;
use App\Components\HttpCodes;
use App\Components\ICurrencyApi;
use App\Components\Response;
use App\Components\SimpleAuth;
use ErrorException;
use JetBrains\PhpStorm\Pure;

class RequestHandler
{
    private ICurrencyApi $api;
    private AuthContext $auth;

    /**
     * Спсиок доступных методов API, с указанием необходимости авторизации.
     * @var boolean[]
     */
    private const API_METHODS_AUTH = [
        'rates' => true,
        'convert' => true,
        'token' => false
    ];

    /**
     * Конструктор класса RequestHandler.
     */
    #[Pure]
    public function __construct() {
        $this->auth = new AuthContext(new SimpleAuth());
        $this->api = new CurrencyApiCommission(new CurrencyApi()); // Декоратор "CurrencyApiCommission" для добавления комиссии методам API.
    }

    /**
     * Логика обработки запроса.
     * @return void
     * @throws ErrorException
     */
    public function init(): void {
        // Проверяем допустимость метода запроса.
        $method = $_REQUEST['method'];
        if (empty($method) || !in_array(needle: $method, haystack: array_keys(array: self::API_METHODS_AUTH))) {
            throw new ErrorException(
                message: 'Invalid request method',
                code: HttpCodes::HTTP_I_AM_A_TEAPOT
            );
        }

        // Проверяем необходимость авторизации для метода.
        if (self::API_METHODS_AUTH[$method] === true && !$this->auth->runCheck()) {
            throw new ErrorException(
                message: 'Invalid token',
                code: HttpCodes::HTTP_FORBIDDEN
            );
        }

        if ($method === 'token') {
            $data = $this->auth->getToken();
        } elseif ($method === 'rates') {
            $currency = [];
            if (!empty($_REQUEST['currency'])) {
                $currency = str_contains($_REQUEST['currency'], ',') ?
                    explode(',', $_REQUEST['currency']) : [$_REQUEST['currency']];
            }
            $data = $this->api->rates($currency);
        } else {
            extract(array: $_REQUEST, flags: EXTR_SKIP);

            if (!isset($currency_from, $currency_to, $value)) {
                throw new ErrorException(
                    message: 'Required parameters are missing (method = convert)',
                    code: HttpCodes::HTTP_BAD_REQUEST
                );
            }

            $data = $this->api->convert(
                $currency_from,
                $currency_to,
                $value
            );
        }

        // Отправялем ответ.
        Response::send(payload: $data);
    }
}