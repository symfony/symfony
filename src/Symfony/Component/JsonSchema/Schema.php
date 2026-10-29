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
 * A generated JSON Schema: a root schema and the named definitions it references.
 *
 * Empty schemas are returned as \ArrayObject instances, so that json_encode() turns them into JSON objects.
 *
 * @experimental
 */
final class Schema implements \JsonSerializable
{
    private const LITERAL_KEYWORDS = ['type', 'enum', 'const', 'default', 'example', 'examples', 'required'];
    private const SCHEMA_LIST_KEYWORDS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];
    private const SCHEMA_MAP_KEYWORDS = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];
    private const ANNOTATION_KEYWORDS = ['title', 'description', 'default', 'deprecated', 'readOnly', 'writeOnly', 'example', 'examples', '$comment'];
    private const MERGEABLE_MAP_KEYWORDS = ['properties', 'patternProperties', 'dependentSchemas'];
    // the keywords whose meaning depends on other keywords of the same schema
    private const ADJACENT_KEYWORDS = [
        'additionalProperties' => ['properties', 'patternProperties'],
        'items' => ['prefixItems'],
        'additionalItems' => ['items'],
        'minContains' => ['contains'],
        'maxContains' => ['contains'],
        'then' => ['if'],
        'else' => ['if'],
    ];

    /**
     * @param array<string, mixed>                $root
     * @param array<string, array<string, mixed>> $definitions
     */
    public function __construct(
        private readonly array $root,
        private readonly array $definitions,
        private readonly Dialect $dialect,
    ) {
    }

    /**
     * @return array<string, mixed>|\ArrayObject<string, mixed>
     */
    public function getRoot(): array|\ArrayObject
    {
        return self::encodeSchema($this->root);
    }

    /**
     * @return array<string, array<string, mixed>|\ArrayObject<string, mixed>>
     */
    public function getDefinitions(): array
    {
        return array_map(self::encodeSchema(...), $this->definitions);
    }

    /**
     * Inlines every reference, except the ones closing a cycle: those keep their $ref and definition.
     */
    public function flatten(): self
    {
        $cyclic = [];
        $root = $this->inline($this->root, [], $cyclic);

        $definitions = [];
        while (null !== $name = array_key_first(array_diff_key($cyclic, $definitions))) {
            $definitions[$name] = $this->inline($this->definitions[$name], [$name => true], $cyclic);
        }

        return new self($root, $definitions, $this->dialect);
    }

    /**
     * @return array<string, mixed>|\ArrayObject<string, mixed>
     */
    public function toArray(): array|\ArrayObject
    {
        $document = null !== $this->dialect->schemaUri ? ['$schema' => $this->dialect->schemaUri] : [];
        $document += $this->root;

        if ($this->definitions) {
            $definitions = $this->definitions;
            foreach (array_reverse($this->getDefinitionsPath()) as $segment) {
                $definitions = [$segment => $definitions];
            }

            $document += $definitions;
        }

        return self::encodeSchema($document);
    }

    public function jsonSerialize(): array|\ArrayObject
    {
        return $this->toArray();
    }

    /**
     * @param array<mixed>        $schema
     * @param array<string, true> $stack
     * @param array<string, true> $cyclic
     *
     * @return array<mixed>
     */
    private function inline(array $schema, array $stack, array &$cyclic): array
    {
        $definition = [];
        if (null !== $name = $this->getReferencedDefinition($schema)) {
            if (isset($stack[$name])) {
                $cyclic[$name] = true;
            } else {
                unset($schema['$ref']);
                $definition = $this->inline($this->definitions[$name], $stack + [$name => true], $cyclic);
            }
        }

        foreach ($schema as $keyword => $value) {
            if (!\is_array($value) || \in_array($keyword, self::LITERAL_KEYWORDS, true)) {
                continue;
            }

            if (!\in_array($keyword, self::SCHEMA_LIST_KEYWORDS, true) && !\in_array($keyword, self::SCHEMA_MAP_KEYWORDS, true)) {
                $schema[$keyword] = $this->inline($value, $stack, $cyclic);

                continue;
            }

            foreach ($value as $key => $subSchema) {
                if (\is_array($subSchema)) {
                    $schema[$keyword][$key] = $this->inline($subSchema, $stack, $cyclic);
                }
            }
        }

        return $definition ? $this->merge($definition, $schema) : $schema;
    }

    /**
     * Merges a definition with the keywords next to the reference to it, since both apply.
     *
     * The annotations next to the reference override the ones of the definition.
     * When merging would change what a keyword means, the definition is kept in an allOf instead.
     *
     * @param array<mixed> $definition
     * @param array<mixed> $schema
     *
     * @return array<mixed>
     */
    private function merge(array $definition, array $schema): array
    {
        if ($definition === $schema) {
            return $schema;
        }

        if (!$this->canMerge($definition, $schema)) {
            if (isset($schema['$ref']) && !$this->dialect->supportsRefSiblings) {
                return ['allOf' => [$definition, $schema]];
            }

            return ['allOf' => [$definition, ...$schema['allOf'] ?? []]] + $schema;
        }

        foreach ($schema as $keyword => $value) {
            if (!\array_key_exists($keyword, $definition) || $definition[$keyword] === $value) {
                $definition[$keyword] = $value;
            } elseif ('required' === $keyword) {
                $definition[$keyword] = array_values(array_unique([...$definition[$keyword], ...$value]));
            } elseif ('allOf' === $keyword) {
                $definition[$keyword] = [...$definition[$keyword], ...$value];
            } elseif (\in_array($keyword, self::MERGEABLE_MAP_KEYWORDS, true)) {
                foreach ($value as $key => $subSchema) {
                    $definition[$keyword][$key] = match (true) {
                        !\array_key_exists($key, $definition[$keyword]), true === $definition[$keyword][$key], false === $subSchema => $subSchema,
                        true === $subSchema, false === $definition[$keyword][$key] => $definition[$keyword][$key],
                        default => $this->merge($definition[$keyword][$key], $subSchema),
                    };
                }
            } else {
                $definition[$keyword] = $value;
            }
        }

        return $definition;
    }

    /**
     * @param array<mixed> $definition
     * @param array<mixed> $schema
     */
    private function canMerge(array $definition, array $schema): bool
    {
        foreach ($schema as $keyword => $value) {
            if (\array_key_exists($keyword, $definition) && $definition[$keyword] !== $value && !self::isAnnotation($keyword) && !\in_array($keyword, ['required', 'allOf', ...self::MERGEABLE_MAP_KEYWORDS], true)) {
                return false;
            }
        }

        foreach ([[$definition, $schema], [$schema, $definition]] as [$side, $otherSide]) {
            foreach (array_keys($side) as $keyword) {
                $dependencies = match ($keyword) {
                    '$ref' => $this->dialect->supportsRefSiblings ? [] : array_keys($otherSide),
                    'unevaluatedProperties', 'unevaluatedItems' => array_filter(array_keys($otherSide), static fn (string $otherKeyword): bool => !self::isAnnotation($otherKeyword)),
                    default => array_intersect(array_keys($otherSide), self::ADJACENT_KEYWORDS[$keyword] ?? []),
                };

                if ($dependencies) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function isAnnotation(string $keyword): bool
    {
        return \in_array($keyword, self::ANNOTATION_KEYWORDS, true) || str_starts_with($keyword, 'x-');
    }

    /**
     * @param array<mixed> $schema
     */
    private function getReferencedDefinition(array $schema): ?string
    {
        if (!\is_string($ref = $schema['$ref'] ?? null) || !str_starts_with($ref, $this->dialect->refPath)) {
            return null;
        }

        $name = substr($ref, \strlen($this->dialect->refPath));

        return isset($this->definitions[$name]) ? $name : null;
    }

    /**
     * @return list<string>
     */
    private function getDefinitionsPath(): array
    {
        $pointer = trim(substr($this->dialect->refPath, 1), '/');

        return array_map(static fn (string $segment): string => strtr($segment, ['~1' => '/', '~0' => '~']), explode('/', $pointer));
    }

    /**
     * Empty schemas must be encoded as JSON objects, never as empty JSON arrays.
     *
     * @param array<mixed>|bool $schema
     */
    private static function encodeSchema(array|bool $schema): array|\ArrayObject|bool
    {
        if (\is_bool($schema)) {
            return $schema;
        }

        if ([] === $schema) {
            return new \ArrayObject();
        }

        foreach ($schema as $keyword => $value) {
            // the values of literal keywords and of specification extensions (x-*) are not schemas
            if (!\is_array($value) || \in_array($keyword, self::LITERAL_KEYWORDS, true) || str_starts_with($keyword, 'x-')) {
                continue;
            }

            $schema[$keyword] = match (true) {
                \in_array($keyword, self::SCHEMA_LIST_KEYWORDS, true) => array_map(self::encodeSchema(...), $value),
                \in_array($keyword, self::SCHEMA_MAP_KEYWORDS, true) => self::encodeMap(array_map(self::encodeSchema(...), $value)),
                'dependentRequired' === $keyword => self::encodeMap($value),
                'components' === $keyword => array_map(static fn (array $schemas): array => array_map(self::encodeSchema(...), $schemas), $value),
                default => self::encodeSchema($value),
            };
        }

        return $schema;
    }

    /**
     * Maps must be encoded as JSON objects, also when they are empty or when their keys are 0, 1, 2...
     *
     * @param array<mixed> $map
     *
     * @return array<mixed>|\ArrayObject<int, mixed>
     */
    private static function encodeMap(array $map): array|\ArrayObject
    {
        return array_is_list($map) ? new \ArrayObject($map) : $map;
    }
}
