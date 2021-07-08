<?php

namespace App;

use App\Api\CurrencyApi;
use App\Api\CurrencyApiCommission;
use App\Api\ICurrencyApi;
use App\Response\Response;
use App\Utils\HttpCodes;
use ErrorException;
use JetBrains\PhpStorm\Pure;

/**
 * Class RequestHandler
 * @package App
 */
class RequestHandler
{
    private ICurrencyApi $api;

    /**
     * Спсиок доступных методов API.
     */
    private const API_METHODS = [
        'rates',
        'convert'
    ];

    /**
     * Конструктор класса RequestHandler.
     */
    #[Pure]
    public function __construct()
    {
        // Декоратор "CurrencyApiCommission" для добавления комиссии методам API.
        $this->api = new CurrencyApiCommission(new CurrencyApi());
    }

    /**
     * Логика обработки запроса.
     * @return void
     * @throws ErrorException
     */
    public function init(): void
    {
        // Проверяем допустимость метода запроса.
        $method = $_REQUEST['method'];
        if (empty($method) || !in_array(
                needle: $method,
                haystack: self::API_METHODS
            )) {
            throw new ErrorException(
                'Invalid request method',
                HttpCodes::HTTP_I_AM_A_TEAPOT
            );
        }

        if ($method === 'rates') {
            /* Получение курса валют */
            $currency = [];
            if (!empty($_REQUEST['currency'])) {
                $currency = str_contains($_REQUEST['currency'], ',') ?
                    explode(
                        ',',
                        $_REQUEST['currency']
                    ) : [$_REQUEST['currency']];
            }
            $data = $this->api->rates($currency);
        } else {
            /* Конвертация валюты */
            extract(array: $_REQUEST, flags: EXTR_SKIP);

            if (!isset($currency_from, $currency_to, $value)) {
                throw new ErrorException(
                    'Required parameters are missing (method = convert)',
                    HttpCodes::HTTP_BAD_REQUEST
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
