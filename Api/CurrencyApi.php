<?php

namespace App\Api;

use App\Request\RemoteQuery;
use ErrorException;
use JetBrains\PhpStorm\ArrayShape;
use App\Utils\HttpCodes;

/**
 * Interface ICurrencyApi
 * @package App\Api
 */
interface ICurrencyApi
{
    public function rates(array $currency = []): array;

    public function convert(
        string $currency_from,
        string $currency_to,
        string $value
    ): array;
}

/**
 * Class CurrencyApi
 * @package Api
 */
class CurrencyApi implements ICurrencyApi
{
    /**
     * URL для получения данных обмена валют.
     */
    private const TICKER_URL = 'https://blockchain.info/ticker';

    /**
     * Получение курсов валют.
     * @param array $currency
     * @return array
     * @throws ErrorException
     */
    public function rates(array $currency = []): array
    {
        $data = RemoteQuery::getJson(url: self::TICKER_URL);

        $result = [];
        foreach ($data as $k => $v) {
            $result[$k] = $v['last'];
        }

        if ($currency) {
            // Вернуть только указанные в параметре %$currency% валюты.
            $result = array_filter(
                $result,
                fn($item) => in_array(needle: $item, haystack: $currency),
                ARRAY_FILTER_USE_KEY
            );

            if (!$result) {
                throw new ErrorException(
                    'No data available for the specified currency',
                    HttpCodes::HTTP_BAD_REQUEST
                );
            }
        }

        asort($result);

        return $result;
    }

    /**
     * Запрос на обмен валюты.
     * @param string $currency_from
     * @param string $currency_to
     * @param string $value
     * @return array
     * @throws ErrorException
     */
    #[ArrayShape([
        'currency_from' => "string",
        'currency_to' => "string",
        'value' => "string",
        'converted_value' => "string",
        'rate' => "string"
    ])]
    public function convert(
        string $currency_from,
        string $currency_to,
        string $value
    ): array {
        $currency_from = strtoupper($currency_from);
        $currency_to = strtoupper($currency_to);

        // Проверяем параметр %value%
        if (is_numeric($value)) {
            if ($value <= 0) {
                throw new ErrorException(
                    'The %value% parameter must be > 0',
                    HttpCodes::HTTP_BAD_REQUEST
                );
            }
        } else {
            throw new ErrorException(
                'The %value% parameter must be numeric',
                HttpCodes::HTTP_BAD_REQUEST
            );
        }

        // Запрашиваем данные для указанной валюты.
        $target = $currency_from === 'BTC' ? $currency_to : $currency_from;
        if (!($currencyRate = $this->rates([$target]))) {
            throw new ErrorException(
                "Unable to get data for specified currency ($target)",
                HttpCodes::HTTP_BAD_REQUEST
            );
        }
        $currencyRate = $currencyRate[$target];

        // Выясняем направление конвертации
        if ($currency_from === 'BTC' /* BTC => %CURRENCY% */) {
            // Конвертируем
            $converted_value = bcmul(
                num1: $currencyRate,
                num2: $value,
                scale: 2
            );

            // Проверяем минимальный объем валюты
            if (bccomp(num1: $converted_value, num2: '0.01', scale: 3) === -1) {
                throw new ErrorException(
                    'Currency volume must be >= 0.01',
                    HttpCodes::HTTP_BAD_REQUEST
                );
            }
        } elseif ($currency_to === 'BTC') {
            // %CURRENCY% => BTC

            // Проверяем минимальный объем валюты
            if (bccomp(num1: $value, num2: '0.01', scale: 3) === -1) {
                throw new ErrorException(
                    'Currency volume must be >= 0.01',
                    HttpCodes::HTTP_BAD_REQUEST
                );
            }

            // Конвертируем
            $converted_value = bcdiv(
                num1: $value,
                num2: $currencyRate,
                scale: 10
            );
        } else {
            throw new ErrorException(
                'Unable to determine the direction of conversion',
                HttpCodes::HTTP_BAD_REQUEST
            );
        }

        if ($value >= 0.1) {
            $value = number_format(
                num: $value,
                decimals: 2,
                thousands_separator: ''
            );
        }

        // Процент сервиса.
        $rate = number_format(
            num: CurrencyApiCommission::CONVERT_COMMISSION,
            decimals: 2,
            thousands_separator: ''
        );

        return [
            'currency_from' => $currency_from,
            'currency_to' => $currency_to,
            'value' => $value,
            'converted_value' => $converted_value,
            'rate' => $rate
        ];
    }
}
