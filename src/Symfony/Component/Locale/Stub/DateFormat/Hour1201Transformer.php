<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Stub\DateFormat;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Hour1201Transformer extends HourTransformer
{
    public function format(\DateTime $dateTime, $length)
    {
        return $this->padLeft($dateTime->format('g'), $length);
    }

    public function getMktimeHour($hour, $marker = null)
    {
        if ('PM' !== $marker && 12 === $hour) {
            $hour = 0;
        } elseif ('PM' === $marker && 12 !== $hour) {
            // If PM and hour is not 12 (1-12), sum 12 hour
            $hour = $hour + 12;
        }

        return $hour;
    }

    public function getReverseMatchingRegExp($length)
    {
        return '\d{1,2}';
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'hour' => (int) $matched,
            'hourInstance' => $this
        );
    }
}
