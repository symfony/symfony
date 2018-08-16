<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * Validates that values are a multiple of the given number.
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class DivisibleByValidator extends AbstractComparisonValidator
{
    /**
     * {@inheritdoc}
     */
    protected function compareValues($value1, $value2)
    {
        $value1 = \abs($value1);
        $value2 = \abs($value2);
        $epsilon = 0.0000001;

        // can't divide by 0
        if ($value2 < $epsilon) {
            return false;
        }

        // 0 is divisible by everything
        if ($value1 < $epsilon) {
            return true;
        }

        // if the divisor is larger than the dividend, it will never cleanly divide
        if ($value2 > $value1) {
            return false;
        }

        return \abs($value1 - round($value1 / $value2) * $value2) < $epsilon;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode()
    {
        return DivisibleBy::NOT_DIVISIBLE_BY;
    }
}
