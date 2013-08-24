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
 * Parser and formatter for 12 hour format (1-12)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @since v2.3.0
 */
class Hour1201Transformer extends HourTransformer
{
    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function format(\DateTime $dateTime, $length)
    {
        return $this->padLeft($dateTime->format('g'), $length);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function normalizeHour($hour, $marker = null)
    {
        if ('PM' !== $marker && 12 === $hour) {
            $hour = 0;
        } elseif ('PM' === $marker && 12 !== $hour) {
            // If PM and hour is not 12 (1-12), sum 12 hour
            $hour += 12;
        }

        return $hour;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getReverseMatchingRegExp($length)
    {
        return '\d{1,2}';
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'hour' => (int) $matched,
            'hourInstance' => $this
        );
    }
}
