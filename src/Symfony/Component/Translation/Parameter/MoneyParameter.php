<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Parameter;

use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Symfony\Contracts\Translation\ParameterInterface;

/**
 * Wrapper around PHP NumberFormatter for money
 * The provided currency is used instead of the locale's currency.
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
class MoneyParameter implements ParameterInterface
{
    private $value;
    private $currency;
    private $style;

    private $formatters = [];

    public function __construct($value, string $currency, int $style = \NumberFormatter::CURRENCY)
    {
        $this->value = $value;
        $this->currency = $currency;
        $this->style = $style;
    }

    public function format(string $locale = null): string
    {
        if (!isset($this->formatters[$locale])) {
            $this->formatters[$locale] = new \NumberFormatter($locale, $this->style);
        }

        return $this->formatters[$locale]->formatCurrency($this->value, $this->currency);
    }

    /**
     * Short-hand to instantiate from a Money instance.
     */
    public static function fromMoney(Money $money, int $style = \NumberFormatter::CURRENCY): self
    {
        $currencies = new ISOCurrencies();
        $moneyFormatter = new DecimalMoneyFormatter($currencies, $style);

        return new self($moneyFormatter->format($money), $money->getCurrency()->getCode());
    }
}
