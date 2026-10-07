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
 * @experimental
 */
final class Schema implements \JsonSerializable
{
    private const LITERAL_KEYWORDS = ['type', 'enum', 'const', 'default', 'example', 'examples', 'required'];
    private const SCHEMA_LIST_KEYWORDS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];
    private const SCHEMA_MAP_KEYWORDS = ['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'];

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
     * @return array<string, mixed>
     */
    public function getRoot(): array
    {
        return $this->root;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $document = null !== $this->dialect->schemaUri ? ['$schema' => $this->dialect->schemaUri] : [];
        $document += $this->root;

        if (!$this->definitions) {
            return $document;
        }

        $definitions = $this->definitions;
        foreach (array_reverse($this->getDefinitionsPath()) as $segment) {
            $definitions = [$segment => $definitions];
        }

        return $document + $definitions;
    }

    public function jsonSerialize(): array|\ArrayObject
    {
        return self::encodeSchema($this->toArray());
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
        if (null !== $name = $this->getReferencedDefinition($schema)) {
            if (isset($stack[$name])) {
                $cyclic[$name] = true;

                return $schema;
            }

            unset($schema['$ref']);

            return [...$this->inline($this->definitions[$name], $stack + [$name => true], $cyclic), ...$schema];
        }

        foreach ($schema as $keyword => $value) {
            if (\is_array($value) && !\in_array($keyword, self::LITERAL_KEYWORDS, true)) {
                $schema[$keyword] = $this->inline($value, $stack, $cyclic);
            }
        }

        return $schema;
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
     * @param array<mixed> $schema
     */
    private static function encodeSchema(array $schema): array|\ArrayObject
    {
        if ([] === $schema) {
            return new \ArrayObject();
        }

        foreach ($schema as $keyword => $value) {
            if (!\is_array($value) || \in_array($keyword, self::LITERAL_KEYWORDS, true)) {
                continue;
            }

            $schema[$keyword] = match (true) {
                \in_array($keyword, self::SCHEMA_LIST_KEYWORDS, true), \in_array($keyword, self::SCHEMA_MAP_KEYWORDS, true) => array_map(self::encodeSchema(...), $value),
                'components' === $keyword => array_map(static fn (array $schemas): array => array_map(self::encodeSchema(...), $schemas), $value),
                default => self::encodeSchema($value),
            };
        }

        return $schema;
    }
}
