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
 * Parser and formatter for minute format.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 */
class MinuteTransformer extends Transformer
{
    /**
     * {@inheritdoc}
     */
    public function format(\DateTime $dateTime, $length)
    {
        $minuteOfHour = (int) $dateTime->format('i');

        return $this->padLeft($minuteOfHour, $length);
    }

    /**
     * {@inheritdoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        return 1 === $length ? '\d{1,2}' : '\d{'.$length.'}';
    }

    /**
     * {@inheritdoc}
     */
    public function extractDateOptions($matched, $length)
    {
        return [
            'minute' => (int) $matched,
        ];
    }
}
