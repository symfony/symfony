<?php

namespace Symfony\Component\Translation;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Range tests if a given number belongs to a given range.
 *
 * A range can represent a finite set of numbers:
 *
 *  {1,2,3,4}
 *
 * A range can represent numbers between two numbers:
 *
 *  [1, +Inf]
 *  ]-1,2[
 *
 * The left delimiter can be [ (inclusive) or ] (exclusive).
 * The right delimiter can be [ (exclusive) or ] (inclusive).
 * Beside numbers, you can use -Inf and +Inf for the infinite.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Range
{
    /**
     * Tests if the given number is in the range.
     *
     * @param integer $number A number
     * @param string  $range  A range of numbers
     */
    static public function test($number, $range)
    {
        $range = trim($range);

        if (!preg_match('/^'.self::getRangeRegexp().'$/x', $range, $matches)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid range expression.', $range));
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
                &&
                (']' === $matches['right_delimiter'] ? $number <= $rightNumber : $number < $rightNumber)
            ;
        }

        return false;
    }

    /**
     * Returns a Regexp that matches valid ranges.
     *
     * @return string A Regexp (without the delimiters)
     */
    static public function getRangeRegexp()
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

    static protected function convertNumber($number)
    {
        if ('-Inf' === $number) {
            return log(0);
        } elseif ('+Inf' === $number || 'Inf' === $number) {
            return -log(0);
        } else {
            return (int) $number;
        }
    }
}
