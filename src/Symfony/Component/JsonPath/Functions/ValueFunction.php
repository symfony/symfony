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

use Symfony\Component\JsonPath\Nothing;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class ValueFunction implements JsonPathComparableFunctionInterface
{
    use JsonPathFunctionArgumentTrait;

    public function __invoke(array $args, mixed $context): mixed
    {
        $results = $args[0] ?? [];
        if (!\is_array($results)) {
            return $results;
        }

        return 1 === \count($results) ? $results[0] : Nothing::Nothing;
    }

    public function validate(string $name, array $args): void
    {
        $this->assertArgumentsCount($name, $args, 1);
    }
}
