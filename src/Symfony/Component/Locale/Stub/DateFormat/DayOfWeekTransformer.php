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
class DayOfWeekTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $dayOfWeek = $dateTime->format('l');
        switch ($length) {
            case 4:
                return $dayOfWeek;
            case 5:
                return $dayOfWeek[0];
            default:
                return substr($dayOfWeek, 0, 3);
        }
    }

    public function getReverseMatchingRegExp($length)
    {
        $dayOfWeek = $dateTime->format('l');
        switch ($length) {
            case 4:
                return 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday';
            case 5:
                return '[MTWFS]';
            default:
                return 'Mon|Tue|Wed|Thu|Fri|Sat|Sun';
        }
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'hour' => (int) $matched,
        );
    }
}
