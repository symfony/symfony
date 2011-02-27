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
class Hour2401Transformer extends HourTransformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $hourOfDay = $dateTime->format('G');
        $hourOfDay = ('0' == $hourOfDay) ? '24' : $hourOfDay;
        return $this->padLeft($hourOfDay, $length);
    }

    public function getMktimeHour($hour, $marker = null)
    {
        if (null !== $marker || 24 === $hour) {
            $hour = 0;
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
