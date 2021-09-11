<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Translatable;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Wrapper around PHP NumberFormatter for decimal values.
 *
 * Implementation of the ICU recommendation to first format advanced parameters before translation.
 *
 * @see https://unicode-org.github.io/icu/userguide/format_parse/messages/#format-the-parameters-separately-recommended
 *
 * @author Sylvain Fabre <syl.fabre@gmail.com>
 */
class DecimalTranslatable implements TranslatableInterface
{
    private float|int $value;
    private int $style;

    private static array $formatters = [];

    public function __construct(float|int $value, int $style = \NumberFormatter::DECIMAL)
    {
        $this->value = $value;
        $this->style = $style;
    }

    public function trans(TranslatorInterface $translator, string $locale = null): string
    {
        if (!$locale) {
            $locale = $translator->getLocale();
        }

        $key = implode('.', [$locale, $this->style]);
        if (!isset(self::$formatters[$key])) {
            self::$formatters[$key] = new \NumberFormatter($locale, $this->style);
        }

        return self::$formatters[$key]->format($this->value);
    }
}
