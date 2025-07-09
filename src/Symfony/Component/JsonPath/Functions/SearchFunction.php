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

use Symfony\Component\JsonPath\JsonPathUtils;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class SearchFunction implements JsonPathFunctionInterface
{
    use JsonPathFunctionArgumentTrait;

    public function __invoke(array $args, mixed $context): bool
    {
        $valueResults = $args[0] ?? [];
        $patternResults = $args[1] ?? [];

        $value = \is_array($valueResults) ? ($valueResults[0] ?? null) : $valueResults;
        $pattern = \is_array($patternResults) ? ($patternResults[0] ?? null) : $patternResults;

        return \is_string($value) && \is_string($pattern) && @preg_match('/'.JsonPathUtils::normalizeRegex($pattern).'/u', $value);
    }

    public function validate(string $name, array $args): void
    {
        $this->assertArgumentsCount($name, $args, 2);
    }
}
