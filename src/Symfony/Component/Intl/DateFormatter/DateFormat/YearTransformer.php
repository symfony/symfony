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
 * Parser and formatter for year format
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class YearTransformer extends Transformer
{
    /**
     * {@inheritDoc}
     */
    public function format(\DateTime $dateTime, $length)
    {
        if (2 === $length) {
            return $dateTime->format('y');
        }

        return $this->padLeft($dateTime->format('Y'), $length);
    }

    /**
     * {@inheritDoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        return 2 === $length ? '\d{2}' : '\d{4}';
    }

    /**
     * {@inheritDoc}
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'year' => (int) $matched,
        );
    }
}
