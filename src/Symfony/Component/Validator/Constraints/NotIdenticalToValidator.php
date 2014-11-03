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
 * Validates values aren't identical (!==).
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 *
 * @deprecated Deprecated as of Symfony 2.6, to be removed in version 3.0.
 *             Use {@link Expression} instead.
 */
class NotIdenticalToValidator extends AbstractComparisonValidator
{
    /**
     * {@inheritdoc}
     */
    protected function compareValues($value1, $value2)
    {
        return $value1 !== $value2;
    }
}
