<?php

namespace App\Api;

/**
 * Class CurrencyApiDecorator
 * @package App\Api
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
    public function __construct(ICurrencyApi $component)
    {
        $this->component = $component;
    }

    /**
     * @param array $currency
     * @return array
     */
    public function rates(array $currency = []): array
    {
        return $this->component->rates($currency);
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
        return $this->component->convert($currency_from, $currency_to, $value);
    }
}
