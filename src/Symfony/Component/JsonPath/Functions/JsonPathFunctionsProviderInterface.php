<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Functions;

use Symfony\Component\JsonPath\Exception\InvalidArgumentException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
interface JsonPathFunctionsProviderInterface
{
    public function hasFunction(string $name): bool;

    /**
     * @throws InvalidArgumentException When the function is not available
     */
    public function getFunction(string $name): JsonPathFunctionInterface;
}
