<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

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
        if (2 == $length) {
            return $dateTime->format('y');
        } else {
            return $this->padLeft($dateTime->format('Y'), $length);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        if (2 == $length) {
            $regExp = '\d{2}';
        } else {
            $regExp = '\d{4}';
        }

        return $regExp;
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
