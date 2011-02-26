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
class MinuteTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $minuteOfHour = (int) $dateTime->format('i');
        return $this->padLeft($minuteOfHour, $length);
    }

    public function getReverseMatchingRegExp($length)
    {
        if (1 == $length) {
            return '\d{1,2}';
        } else {
            return "\d{$length}";
        }
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'minute' => (int) $matched,
        );
    }
}
