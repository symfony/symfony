<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

/**
 * Tests if a given number belongs to a given math interval.
 *
 * An interval can represent a finite set of numbers:
 *
 *  {1,2,3,4}
 *
 * An interval can represent numbers between two numbers:
 *
 *  [1, +Inf]
 *  ]-1,2[
 *
 * The left delimiter can be [ (inclusive) or ] (exclusive).
 * The right delimiter can be [ (exclusive) or ] (inclusive).
 * Beside numbers, you can use -Inf and +Inf for the infinite.
 *
 * @see http://en.wikipedia.org/wiki/Interval_%28mathematics%29#The_ISO_notation
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Interval
{
    /**
     * Tests if the given number is in the math interval.
     *
     * @param integer $number   A number
     * @param string  $interval An interval
     */
    static public function test($number, $interval)
    {
        $interval = trim($interval);

        if (!preg_match('/^'.self::getIntervalRegexp().'$/x', $interval, $matches)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid interval.', $interval));
        }

        if ($matches[1]) {
            foreach (explode(',', $matches[2]) as $n) {
                if ($number == $n) {
                    return true;
                }
            }
        } else {
            $leftNumber = self::convertNumber($matches['left']);
            $rightNumber = self::convertNumber($matches['right']);

            return
                ('[' === $matches['left_delimiter'] ? $number >= $leftNumber : $number > $leftNumber)
                && (']' === $matches['right_delimiter'] ? $number <= $rightNumber : $number < $rightNumber)
            ;
        }

        return false;
    }

    /**
     * Returns a Regexp that matches valid intervals.
     *
     * @return string A Regexp (without the delimiters)
     */
    static public function getIntervalRegexp()
    {
        return <<<EOF
        ({\s*
            (\-?\d+[\s*,\s*\-?\d+]*)
        \s*})

            |

        (?<left_delimiter>[\[\]])
            \s*
            (?<left>-Inf|\-?\d+)
            \s*,\s*
            (?<right>\+?Inf|\-?\d+)
            \s*
        (?<right_delimiter>[\[\]])
EOF;
    }

    static private function convertNumber($number)
    {
        if ('-Inf' === $number) {
            return log(0);
        } elseif ('+Inf' === $number || 'Inf' === $number) {
            return -log(0);
        }

        return (int) $number;
    }
}
