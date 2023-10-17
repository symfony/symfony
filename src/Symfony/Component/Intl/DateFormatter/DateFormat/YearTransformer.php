<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\DateFormatter\DateFormat;

/**
 * Parser and formatter for year format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 *
 * @deprecated since Symfony 5.3, use symfony/polyfill-intl-icu ^1.21 instead
 */
class YearTransformer extends Transformer
{
    
    public function format(\DateTime $dateTime, int $length): string
    {
        if (2 === $length) {
            return $dateTime->format('y');
        }

        return $this->padLeft($dateTime->format('Y'), $length);
    }

    public function getReverseMatchingRegExp(int $length): string
    {
        return 2 === $length ? '\d{2}' : '\d{1,4}';
    }

    public function extractDateOptions(string $matched, int $length): array
    {
        return [
            'year' => (int) $matched,
        ];
    }
}
