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
 * Writes the keywords that do not depend on the dialect, on schemas that may allow null with any null syntax.
 *
 * @experimental
 */
final class DialectKeywords
{
    /**
     * Restricts a schema to the given values, null included when the schema allows it.
     *
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
     * Tells whether a schema allows null, with a type union, the nullable flag or an anyOf branch.
     *
     * @param array<string, mixed> $schema
     */
    public static function allowsNull(array $schema): bool
    {
        return true === ($schema['nullable'] ?? false)
            || \in_array('null', (array) ($schema['type'] ?? []), true)
            || array_any($schema['anyOf'] ?? [], static fn (array|bool $branch): bool => \is_bool($branch) ? $branch : self::allowsNull($branch));
    }

    /**
     * Restricts a schema so that it no longer allows null.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public static function withoutNull(array $schema): array
    {
        unset($schema['nullable']);

        if (isset($schema['type'])) {
            $types = array_values(array_filter((array) $schema['type'], static fn (mixed $type): bool => 'null' !== $type));

            if (!$types) {
                return ['enum' => []];
            }

            $schema['type'] = 1 === \count($types) ? $types[0] : $types;
        }

        if (isset($schema['enum'])) {
            $schema['enum'] = array_values(array_filter($schema['enum'], static fn (mixed $value): bool => null !== $value));

            if (!$schema['enum']) {
                return ['enum' => []];
            }
        }

        if (\array_key_exists('const', $schema) && null === $schema['const']) {
            return ['enum' => []];
        }

        if (isset($schema['anyOf'])) {
            $branches = [];

            foreach ($schema['anyOf'] as $branch) {
                if (true === $branch) {
                    $branches[] = ['not' => ['type' => 'null']];

                    continue;
                }

                if (false === $branch) {
                    continue;
                }

                $branch = self::withoutNull($branch);

                if ([] !== ($branch['enum'] ?? null)) {
                    $branches[] = $branch;
                }
            }

            if (!$branches) {
                return ['enum' => []];
            }

            $schema['anyOf'] = $branches;
        }

        return $schema;
    }
}
