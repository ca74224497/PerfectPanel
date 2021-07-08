<?php

namespace App\Api;

/**
 * Class CurrencyApiCommission Добавление комиссии методам API.
 * @package App\Api
 */
class CurrencyApiCommission extends CurrencyApiDecorator
{
    /**
     * Комиссия для метода "rates".
     */
    public const RATES_COMMISSION = 2;
    /**
     * Комиссия для метода "convert".
     */
    public const CONVERT_COMMISSION = 2;

    /**
     * @param array $currency
     * @return array
     */
    public function rates(array $currency = []): array
    {
        $data = parent::rates($currency);

        // Добавляем комиссию
        foreach ($data as &$item) {
            $commission = bcmul(
                num1: bcdiv(num1: $item, num2: 100, scale: 4),
                num2: self::RATES_COMMISSION,
                scale: 4
            );
            $item = bcadd(
                num1: $item,
                num2: $commission,
                scale: 2
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
    public function convert(
        string $currency_from,
        string $currency_to,
        string $value
    ): array {
        $data = parent::convert($currency_from, $currency_to, $value);

        // Добавляем комиссию
        $commission = bcmul(
            num1: bcdiv(num1: $data['converted_value'], num2: 100, scale: 4),
            num2: self::CONVERT_COMMISSION,
            scale: 4
        );
        $data['converted_value'] = bcsub(
            num1: $data['converted_value'],
            num2: $commission,
            scale: $currency_from === 'BTC' ? 2 : 10
        );

        // @todo: Уточнить нужно ли проверять минимальный объем валюты после комиссии?
        /*if (bccomp(
                num1: $data['converted_value'],
                num2: '0.01',
                scale: 3
            ) === -1) {
            throw new ErrorException(
                'Currency volume after commission must be >= 0.01',
                HttpCodes::HTTP_BAD_REQUEST
            );
        }*/

        return $data;
    }
}
