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
 * Parser and formatter for 12 hour format (0-11)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Hour1200Transformer extends HourTransformer
{
    /**
     * {@inheritDoc}
     */
    public function format(\DateTime $dateTime, $length)
    {
        $hourOfDay = $dateTime->format('g');
        $hourOfDay = ('12' == $hourOfDay) ? '0' : $hourOfDay;
        return $this->padLeft($hourOfDay, $length);
    }

    /**
     * {@inheritDoc}
     */
    public function normalizeHour($hour, $marker = null)
    {
        if ('PM' === $marker) {
            $hour += 12;
        }

        return $hour;
    }

    /**
     * {@inheritDoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        return '\d{1,2}';
    }

    /**
     * {@inheritDoc}
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'hour' => (int) $matched,
            'hourInstance' => $this
        );
    }
}
