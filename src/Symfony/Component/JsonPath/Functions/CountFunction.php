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

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class CountFunction implements JsonPathComparableFunctionInterface
{
    use JsonPathFunctionArgumentTrait;

    public function __invoke(array $args, mixed $context): int
    {
        $results = $args[0] ?? [];

        return \is_array($results) ? \count($results) : 0;
    }

    public function validate(string $name, array $args): void
    {
        $this->assertArgumentsCount($name, $args, 1);
        $this->assertIsQuery($name, trim($args[0]));
    }
}
