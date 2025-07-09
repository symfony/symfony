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
trait JsonPathFunctionArgumentTrait
{
    /**
     * @param list<string> $args
     */
    public function assertArgumentsCount(string $name, array $args, int $expectedCount): void
    {
        if (\count($args) !== $expectedCount) {
            throw new InvalidArgumentException(\sprintf('the JsonPath function "%s" requires exactly %d argument(s).', $name, $expectedCount));
        }
    }

    public function assertIsSingularQuery(string $name, string $arg): void
    {
        if (preg_match('/@\.\*/', trim($arg))) {
            throw new InvalidArgumentException(\sprintf('the JsonPath function "%s" argument must be a singular query.', $name));
        }
    }

    public function assertIsQuery(string $name, string $arg): void
    {
        if (preg_match('/^(true|false|null|\d+(\.\d+)?([eE][+-]?\d+)?|\'[^\']*\'|"[^"]*")$/', $arg)) {
            throw new InvalidArgumentException(\sprintf('the JsonPath function "%s" requires a query argument, not a literal.', $name));
        }
    }
}
