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

use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\TypeInfo\Type;

/**
 * Collects the definitions of the schemas generated for one document, for example an OpenAPI document.
 *
 * Pass the same instance to every SchemaGeneratorInterface::generate() call that contributes to the document:
 * a definition is built once per document, and its name describes the same shape in every schema of the document.
 *
 * @experimental
 */
final class SchemaDocument
{
    private ?Dialect $dialect = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $definitions = [];

    /**
     * Definition name => class and shape fingerprint it was reserved for.
     *
     * @var array<string, array{class-string, string}>
     */
    private array $reservations = [];

    private ?\Closure $describer = null;

    /**
     * @return array<string, array<string, mixed>|\ArrayObject<string, mixed>>
     */
    public function getDefinitions(): array
    {
        return null !== $this->dialect ? (new Schema([], $this->definitions, $this->dialect))->getDefinitions() : [];
    }

    /**
     * Generates the schema of a type in this document, for extensions that describe a value with other types.
     *
     * It is only available while a schema is generated in this document.
     *
     * @param DefinitionParent|null $parent the property through which the type is reached
     *
     * @return array<string, mixed>
     */
    public function describe(Type $type, Configuration $config, ?DefinitionParent $parent = null): array
    {
        if (null === $this->describer) {
            throw new LogicException('Types can only be described while a schema is generated in this document.');
        }

        return ($this->describer)($type, $config, $parent);
    }

    /**
     * @internal
     *
     * @param (\Closure(Type, Configuration, ?DefinitionParent): array<string, mixed>)|null $describer
     *
     * @return (\Closure(Type, Configuration, ?DefinitionParent): array<string, mixed>)|null the previous describer
     */
    public function setDescriber(?\Closure $describer): ?\Closure
    {
        $previous = $this->describer;
        $this->describer = $describer;

        return $previous;
    }

    /**
     * Reserves a definition name before its definition is built, which is what stops self-referencing classes from recursing forever.
     *
     * @internal
     *
     * @param class-string $class
     *
     * @return array{class-string, string}|null null when the name was free, the class and fingerprint it is reserved for otherwise
     */
    public function reserve(string $name, string $class, string $fingerprint, Dialect $dialect): ?array
    {
        $this->dialect ??= $dialect;

        if ($this->dialect->refPath !== $dialect->refPath) {
            throw new LogicException(\sprintf('The definitions of this document are referenced as "%s", they cannot also be referenced as "%s".', $this->dialect->refPath, $dialect->refPath));
        }

        if (isset($this->reservations[$name])) {
            return $this->reservations[$name];
        }

        $this->reservations[$name] = [$class, $fingerprint];
        $this->definitions[$name] = [];

        return null;
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $definition
     */
    public function define(string $name, array $definition): void
    {
        $this->definitions[$name] = $definition;
    }

    /**
     * Creates the schema of a root, with the definitions it references directly or not.
     *
     * @internal
     *
     * @param array<string, mixed> $root
     */
    public function createSchema(array $root, Dialect $dialect): Schema
    {
        $definitions = [];
        $pending = [$root];

        while (null !== $schema = array_pop($pending)) {
            array_walk_recursive($schema, function (mixed $value, int|string $keyword) use ($dialect, &$definitions, &$pending): void {
                if ('$ref' !== $keyword || !\is_string($value) || !str_starts_with($value, $dialect->refPath)) {
                    return;
                }

                $name = substr($value, \strlen($dialect->refPath));

                if (isset($definitions[$name]) || !isset($this->definitions[$name])) {
                    return;
                }

                $definitions[$name] = true;
                $pending[] = $this->definitions[$name];
            });
        }

        return new Schema($root, array_intersect_key($this->definitions, $definitions), $dialect);
    }
}
