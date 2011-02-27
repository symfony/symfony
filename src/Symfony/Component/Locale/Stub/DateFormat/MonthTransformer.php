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

use Symfony\Component\Locale\Exception\MethodArgumentValueNotImplementedException;

/**
 * Parser and formatter for date formats
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class MonthTransformer extends Transformer
{
    private static $months = array(
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    );

    private static $shortMonths = array();

    private static $flippedMonths = array();

    private static $flippedShortMonths = array();

    public function __construct()
    {
        if (0 == count(self::$shortMonths)) {
            self::$shortMonths = array_map(function($month) {
                return substr($month, 0, 3);
            }, self::$months);

            self::$flippedMonths = array_flip(self::$months);
            self::$flippedShortMonths = array_flip(self::$shortMonths);
        }
    }

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
        switch ($length) {
            case 1:
                return '?P<M>\d{1,2}';
                break;
            case 3:
                return '?P<MMM>' . implode('|', self::$shortMonths);
                break;
            case 4:
                return '?P<MMMM>' . implode('|', self::$months);
                break;
            case 5:
                return '?P<MMMMM>' . '[JFMASOND]';
                break;
            default:
                return "\d{$length}";
                break;
        }
    }

    public function extractDateOptions($matched, $length)
    {
        if (!is_numeric($matched)) {
            if (3 == $length) {
                $matched = self::$flippedShortMonths[$matched] + 1;
            }
            elseif (4 == $length) {
                $matched = self::$flippedMonths[$matched] + 1;
            }
            elseif (5 == $length) {
                // IntlDateFormatter::parse() always returns false for MMMMM or LLLLL
                $matched = false;
            }
        }
        else {
            $matched = (int) $matched;
        }

        return array(
            'month' => $matched,
        );
    }
}
