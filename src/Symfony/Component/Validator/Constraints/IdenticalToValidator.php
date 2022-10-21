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
 * Validates values are identical (===).
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class IdenticalToValidator extends AbstractComparisonValidator
{
    protected function compareValues(mixed $value1, mixed $value2): bool
    {
        return $value1 === $value2;
    }

    protected function getErrorCode(): ?string
    {
        return IdenticalTo::NOT_IDENTICAL_ERROR;
    }
}
