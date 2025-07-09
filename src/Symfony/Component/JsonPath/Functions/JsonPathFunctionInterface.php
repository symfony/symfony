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
interface JsonPathFunctionInterface
{
    /**
     * @param list<string> $args    List of argument strings as they appear in the expression
     * @param mixed        $context The current node to which the function is applied
     */
    public function __invoke(array $args, mixed $context): mixed;

    /**
     * Validates the arguments during tokenization/parsing phase.
     *
     * @param list<string> $args List of argument strings as they appear in the expression
     *
     * @throws InvalidArgumentException When arguments are invalid
     */
    public function validate(string $name, array $args): void;
}
