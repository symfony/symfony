<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formats an amount in the given currency, using the locale of the translation.
 */
final class TranslatableMoney implements TranslatableInterface
{
    private static array $formatters = [];

    public function __construct(
        private int|float $amount,
        private string $currency,
    ) {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $locale = $locale ?: $translator->getLocale();
        $formatter = self::$formatters[$locale] ??= new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        if (false === $money = $formatter->formatCurrency($this->amount, $this->currency)) {
            throw new InvalidArgumentException(\sprintf('Unable to format money in currency "%s" (error #%d): ', $this->currency, $formatter->getErrorCode()).$formatter->getErrorMessage());
        }

        return $money;
    }
}
