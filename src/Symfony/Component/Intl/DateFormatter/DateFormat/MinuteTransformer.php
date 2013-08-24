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
 * Parser and formatter for minute format
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @since v2.3.0
 */
class MinuteTransformer extends Transformer
{
    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function format(\DateTime $dateTime, $length)
    {
        $minuteOfHour = (int) $dateTime->format('i');

        return $this->padLeft($minuteOfHour, $length);
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function getReverseMatchingRegExp($length)
    {
        return 1 === $length ? '\d{1,2}' : '\d{'.$length.'}';
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function extractDateOptions($matched, $length)
    {
        return array(
            'minute' => (int) $matched,
        );
    }
}
