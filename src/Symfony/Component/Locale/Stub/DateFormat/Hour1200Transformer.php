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
class Hour1200Transformer extends HourTransformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $hourOfDay = $dateTime->format('g');
        $hourOfDay = ('12' == $hourOfDay) ? '0' : $hourOfDay;
        return $this->padLeft($hourOfDay, $length);
    }

    public function getMktimeHour($hour, $marker = null)
    {
        if ('PM' === $marker) {
            $hour += 12;
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
