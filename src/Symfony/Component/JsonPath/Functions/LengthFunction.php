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
final class LengthFunction implements JsonPathComparableFunctionInterface
{
    use JsonPathFunctionArgumentTrait;

    public function __invoke(array $args, mixed $context): int|Nothing
    {
        $results = $args[0] ?? [];
        $value = \is_array($results) ? ($results[0] ?? null) : $results;

        return match (true) {
            \is_string($value) => mb_strlen($value, 'UTF-8'),
            $value instanceof Nothing, null === $value => Nothing::Nothing,
            \is_array($value) => \count($value),
            \is_object($value) => \count((array) $value),
            default => 0,
        };
    }

    public function validate(string $name, array $args): void
    {
        $this->assertArgumentsCount($name, $args, 1);
        $this->assertIsSingularQuery($name, $args[0]);
    }
}
