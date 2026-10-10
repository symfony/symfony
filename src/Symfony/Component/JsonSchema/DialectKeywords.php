<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema;

/**
 * Writes the keywords that do not depend on the dialect.
 *
 * @internal
 */
final class DialectKeywords
{
    /**
     * @param array<string, mixed> $schema
     * @param list<mixed>          $values
     *
     * @return array<string, mixed>
     */
    public static function enum(array $schema, array $values): array
    {
        if (self::allowsNull($schema) && !\in_array(null, $values, true)) {
            $values[] = null;
        }

        return [...$schema, 'enum' => $values];
    }

    /**
     * @param array<string, mixed> $schema
     */
    public static function allowsNull(array $schema): bool
    {
        return true === ($schema['nullable'] ?? false)
            || \in_array('null', (array) ($schema['type'] ?? []), true)
            || array_any($schema['anyOf'] ?? [], static fn (array $branch): bool => self::allowsNull($branch));
    }
}
