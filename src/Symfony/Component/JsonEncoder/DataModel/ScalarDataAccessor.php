<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel;

/**
 * Defines the way to access a scalar value.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final readonly class ScalarDataAccessor implements DataAccessorInterface
{
    public function __construct(
        public mixed $value,
    ) {
    }
}
