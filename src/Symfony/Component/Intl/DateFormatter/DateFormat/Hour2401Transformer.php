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
 * Parser and formatter for 24 hour format (1-24)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Hour2401Transformer extends HourTransformer
{
    /**
     * {@inheritDoc}
     */
    public function format(\DateTime $dateTime, $length)
    {
        $hourOfDay = $dateTime->format('G');
        $hourOfDay = ('0' == $hourOfDay) ? '24' : $hourOfDay;

        return $this->padLeft($hourOfDay, $length);
    }

    /**
     * {@inheritDoc}
     */
    public function normalizeHour($hour, $marker = null)
    {
        if ((null === $marker && 24 === $hour) || 'AM' == $marker) {
            $hour = 0;
        } elseif ('PM' == $marker) {
            $hour = 12;
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
