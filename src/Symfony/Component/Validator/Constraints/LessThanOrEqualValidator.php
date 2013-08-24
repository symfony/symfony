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
 * Validates values are less than or equal to the previous (<=).
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 *
 * @since v2.3.0
 */
class LessThanOrEqualValidator extends AbstractComparisonValidator
{
    /**
     * @inheritDoc
     *
     * @since v2.3.0
     */
    protected function compareValues($value1, $value2)
    {
        return $value1 <= $value2;
    }
}
