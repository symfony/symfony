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
 * Writes the keywords whose spelling differs from one dialect to another.
 *
 * @internal
 */
final class DialectKeywords
{
    /**
     * @return array<string, mixed>
     */
    public static function constant(mixed $value, Dialect $dialect): array
    {
        return $dialect->supportsConst ? ['const' => $value] : ['enum' => [$value]];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exclusiveMinimum(int|float $value, Dialect $dialect): array
    {
        return $dialect->supportsExclusiveMinAsNumber ? ['exclusiveMinimum' => $value] : ['minimum' => $value, 'exclusiveMinimum' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function exclusiveMaximum(int|float $value, Dialect $dialect): array
    {
        return $dialect->supportsExclusiveMinAsNumber ? ['exclusiveMaximum' => $value] : ['maximum' => $value, 'exclusiveMaximum' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public static function example(mixed $value, Dialect $dialect): array
    {
        return $dialect->supportsExamples ? ['examples' => [$value]] : ['example' => $value];
    }

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
        return true === ($schema['nullable'] ?? false) || 'null' === ($schema['type'] ?? null) || \in_array('null', (array) ($schema['type'] ?? []), true);
    }
}
