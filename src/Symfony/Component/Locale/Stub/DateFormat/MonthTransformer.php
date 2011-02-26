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
class MonthTransformer extends Transformer
{
    public function format(\DateTime $dateTime, $length)
    {
        $matchLengthMap = array(
            1   => 'n',
            2   => 'm',
            3   => 'M',
            4   => 'F',
        );

        if (isset($matchLengthMap[$length])) {
           return $dateTime->format($matchLengthMap[$length]);
        } else if (5 == $length) {
            return substr($dateTime->format('M'), 0, 1);
        } else {
            return $this->padLeft($dateTime->format('m'), $length);
        }
    }

    public function getReverseMatchingRegExp($length)
    {
        $months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

        switch ($length) {
            case 1:
                return '\d{1,2}';
                break;
            case 3:
                $shortMonths = array_map(function($month) {
                    return substr($month, 0, 2);
                }, $months);
                return implode('|', $shortMonths);
                break;
            case 4:
                return implode('|', $months);
                break;
            case 5:
                return '[JFMASOND]';
                break;
            default:
                return "\d{$length}";
                break;
        }
    }

    public function extractDateOptions($matched, $length)
    {
        return array(
            'month' => (int) $matched,
        );
    }
}
