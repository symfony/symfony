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
        if (!$value2 = abs($value2)) {
            return false;
        }
        if (\is_int($value1 = abs($value1)) && \is_int($value2)) {
            return 0 === ($value1 % $value2);
        }
        if (!$remainder = fmod($value1, $value2)) {
            return true;
        }

        return sprintf('%.12e', $value2) === sprintf('%.12e', $remainder);
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode()
    {
        return DivisibleBy::NOT_DIVISIBLE_BY;
    }
}
