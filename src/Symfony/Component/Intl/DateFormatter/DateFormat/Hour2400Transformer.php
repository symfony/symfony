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
 * Parser and formatter for 24 hour format (0-23).
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 *
 * @internal
 */
class Hour2400Transformer extends HourTransformer
{
    /**
     * {@inheritdoc}
     */
    public function format(\DateTime $dateTime, $length)
    {
        return $this->padLeft($dateTime->format('G'), $length);
    }

    /**
     * {@inheritdoc}
     */
    public function normalizeHour($hour, $marker = null)
    {
        if ('AM' == $marker) {
            $hour = 0;
        } elseif ('PM' == $marker) {
            $hour = 12;
        }

        return $hour;
    }

    /**
     * {@inheritdoc}
     */
    public function getReverseMatchingRegExp($length)
    {
        return '\d{1,2}';
    }

    /**
     * {@inheritdoc}
     */
    public function extractDateOptions($matched, $length)
    {
        return [
            'hour' => (int) $matched,
            'hourInstance' => $this,
        ];
    }
}
