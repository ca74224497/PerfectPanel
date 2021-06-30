<?php
namespace App\Components;

use ErrorException;
use JetBrains\PhpStorm\ArrayShape;

interface ICurrencyApi
{
        public function rates(array $currency = []): array;
        public function convert(
            string $currency_from,
            string $currency_to,
            string $value
        ): array;
}

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
                array: $result,
                callback: fn($item) => in_array(needle: $item, haystack: $currency),
                mode: ARRAY_FILTER_USE_KEY
            );

            if (!$result) {
                throw new ErrorException(
                    message: 'No data available for the specified currency',
                    code: HttpCodes::HTTP_BAD_REQUEST
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
    public function convert(string $currency_from, string $currency_to, string $value): array
    {
        $currency_from = strtoupper($currency_from);
        $currency_to = strtoupper($currency_to);

        // Проверяем параметр %value%
        if (is_numeric($value)) {
            if ((float)$value <= 0) {
                throw new ErrorException(
                    message: 'The %value% parameter must be > 0',
                    code: HttpCodes::HTTP_BAD_REQUEST
                );
            }
        } else {
            throw new ErrorException(
                message: 'The %value% parameter must be numeric',
                code: HttpCodes::HTTP_BAD_REQUEST
            );
        }

        // Запрашиваем данные для указанной валюты.
        $target = $currency_from === 'BTC' ? $currency_to : $currency_from;
        if (!($currencyRate = $this->rates([$target]))) {
            throw new ErrorException(
                message: "Unable to get data for specified currency ($target)",
                code: HttpCodes::HTTP_BAD_REQUEST
            );
        }
        $currencyRate = $currencyRate[$target];

        // Выясняем направление конвертации
        if ($currency_from === 'BTC' /* BTC => %CURRENCY% */) {

            // Конвертируем
            $converted_value = (float)$currencyRate * (float)$value;

            // Проверяем минимальный объем валюты
            if ($converted_value < 0.01) {
                throw new ErrorException(
                    message: 'Currency volume must be >= 0.01',
                    code: HttpCodes::HTTP_BAD_REQUEST
                );
            }

            $converted_value = number_format(
                num: $converted_value,
                decimals: 2,
                thousands_separator: ''
            );

        } elseif ($currency_to === 'BTC') {
            // %CURRENCY% => BTC

            // Проверяем минимальный объем валюты
            if ((float)$value < 0.01) {
                throw new ErrorException(
                    message: 'Currency volume must be >= 0.01',
                    code: HttpCodes::HTTP_BAD_REQUEST
                );
            }

            // Конвертируем
            $converted_value = number_format(
                num: (float)$value / (float)$currencyRate,
                decimals: 10,
                thousands_separator: ''
            );
        } else {
            throw new ErrorException(
                message: 'Unable to determine the direction of conversion',
                code: HttpCodes::HTTP_BAD_REQUEST
            );
        }

        // @todo: уточнить нужно ли форматировать %value% к виду ".00"
        if ((float)$value >= 0.1) {
            $value = number_format(
                num: $value,
                decimals: 2,
                thousands_separator: ''
            );
        }

        return [
            'currency_from' => $currency_from,
            'currency_to' => $currency_to,
            'value' => $value,
            'converted_value' => $converted_value,
            'rate' => $value
        ];
    }
}

/**
 * Class CurrencyApiDecorator
 * @package App\Components
 */
class CurrencyApiDecorator implements ICurrencyApi
{
    /**
     * @var ICurrencyApi
     */
    protected ICurrencyApi $component;

    /**
     * CurrencyApiDecorator constructor.
     * @param ICurrencyApi $component
     */
    public function __construct(ICurrencyApi $component) {
        $this->component = $component;
    }

    /**
     * @param array $currency
     * @return array
     */
    public function rates(array $currency = []): array {
        return $this->component->rates($currency);
    }

    /**
     * @param string $currency_from
     * @param string $currency_to
     * @param string $value
     * @return array
     */
    public function convert(string $currency_from, string $currency_to, string $value): array {
        return $this->component->convert($currency_from, $currency_to, $value);
    }
}

/**
 * Class CurrencyApiCommission Добавление комиссии методам API.
 * @package App\Components
 */
class CurrencyApiCommission extends CurrencyApiDecorator
{
    /**
     * Комиссия для метода "rates".
     */
    private const RATES_COMMISSION = 2;
    /**
     * Комиссия для метода "convert".
     */
    private const CONVERT_COMMISSION = 2;

    /**
     * @param array $currency
     * @return array
     */
    public function rates(array $currency = []): array
    {
        $data = parent::rates($currency);

        // Добавляем комиссию
        foreach ($data as &$item) {
            $item = number_format(
                num: (float)$item + ((float)$item / 100) * self::RATES_COMMISSION,
                decimals: 2,
                thousands_separator: ''
            );
        }

        return $data;
    }

    /**
     * @param string $currency_from
     * @param string $currency_to
     * @param string $value
     * @return array
     */
    public function convert(string $currency_from, string $currency_to, string $value): array
    {
        $data = parent::convert($currency_from, $currency_to, $value);

        // Добавляем комиссию
        $data['converted_value'] =
            (float)$data['converted_value'] - ((float)$data['converted_value'] / 100) * self::CONVERT_COMMISSION;

        // Форматируем согласно ТЗ
        $data['converted_value'] = number_format(
            num: $data['converted_value'],
            decimals: $currency_from === 'BTC' ? 2 : 10,
            thousands_separator: ''
        );

        return $data;
    }
}