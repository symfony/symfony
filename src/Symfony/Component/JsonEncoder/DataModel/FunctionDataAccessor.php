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
 * Defines the way to access data using a function (or a method).
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final readonly class FunctionDataAccessor implements DataAccessorInterface
{
    /**
     * @param list<DataAccessorInterface> $arguments
     */
    public function __construct(
        public string $functionName,
        public array $arguments,
        public ?DataAccessorInterface $objectAccessor = null,
    ) {
    }
}
