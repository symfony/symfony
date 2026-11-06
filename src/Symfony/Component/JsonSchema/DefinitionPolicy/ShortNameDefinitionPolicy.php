<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\DefinitionPolicy;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\DefinitionParent;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

/**
 * Names definitions after the class short name (or the configured prefix for the root definition),
 * suffixed by generic arguments, groups, attributes, ignored attributes, extra attributes, validation groups, format and direction.
 *
 * In a document, a name is owned by the first class claiming it: another class asking for the same
 * name gets one built from more namespace segments instead.
 *
 * Generic names retain their choices when generation fails, just as class-prefix ownership does.
 * Otherwise, a name only depends on the type and the configuration. The rare shapes that still read alike
 * (attributes ["first_name"] and ["first", "name"]) make the generator throw, as they need a custom policy.
 *
 * @experimental
 */
final class ShortNameDefinitionPolicy implements DefinitionPolicyInterface
{
    private const GLUE = '.';

    /**
     * Definition name prefix => class that claimed it first in a document, so two classes sharing a short name
     * (App\Input\Book, App\Output\Book) never end up under the same definition.
     *
     * @var \WeakMap<SchemaDocument, array<string, class-string>>
     */
    private \WeakMap $owners;

    /**
     * @var \WeakMap<SchemaDocument, array<string, string>>
     */
    private \WeakMap $genericOwners;

    /**
     * @var \WeakMap<SchemaDocument, array<string, string>>
     */
    private \WeakMap $genericNames;

    public function __construct()
    {
        $this->owners = new \WeakMap();
        $this->genericOwners = new \WeakMap();
        $this->genericNames = new \WeakMap();
    }

    public function nameFor(ObjectType|GenericType $type, Configuration $config, SchemaDocument $document, ?DefinitionParent $parent = null): string
    {
        $class = ($type instanceof GenericType ? $type->getWrappedType() : $type)->getClassName();
        $prefix = (null === $parent && null !== $config->definitionPrefix ? $this->claim($config->definitionPrefix, $class, $document) : null) ?? $this->claimShortName($class, $document);

        if (null !== $config->definitionName) {
            $name = '' === $config->definitionName ? $prefix : $prefix.'-'.$config->definitionName;
        } else {
            $parts = [];

            if ($config->groups) {
                $parts[] = implode('_', $config->groups);
            }

            if ($config->attributes) {
                $parts[] = self::attributesToString($config->attributes);
            }

            if ($config->ignoredAttributes) {
                $parts[] = 'ignored'.self::GLUE.implode('_', $config->ignoredAttributes);
            }

            if (!$config->allowExtraAttributes) {
                $parts[] = 'closed';
            }

            if (null !== $validationGroups = $config->getValidationGroupNames()) {
                $parts[] = 'validation'.self::GLUE.($validationGroups ? implode('_', $validationGroups) : 'none');
            }

            if (null !== $config->format) {
                $parts[] = 'format'.self::GLUE.$config->format;
            }

            if (null !== $config->direction) {
                $parts[] = strtolower($config->direction->name);
            }

            $name = $parts ? $prefix.'-'.implode('_', $parts) : $prefix;
        }

        if ($type instanceof GenericType && $type->getVariableTypes()) {
            return $this->genericName($type, $prefix, substr($name, \strlen($prefix)), $document);
        }

        return self::sanitize($name);
    }

    /**
     * @param GenericType<ObjectType> $type
     */
    private function genericName(GenericType $type, string $prefix, string $suffix, SchemaDocument $document): string
    {
        $identity = hash('sha256', (string) $type);
        $context = serialize([$identity, $prefix, $suffix]);
        $names = $this->genericNames[$document] ?? [];
        if (isset($names[$context])) {
            return $names[$context];
        }

        $labels = [];
        $keys = [];
        foreach ($type->getVariableTypes() as $argument) {
            $keys[] = (string) $argument;
            if (null !== $label = $this->argumentLabel($argument, $document)) {
                $labels[] = $label;
            }
        }
        $digest = hash('sha256', serialize($keys));
        $label = implode(self::GLUE, $labels);
        if (\count($labels) !== \count($keys) || \strlen($label) > 128) {
            $label = 'generic.'.substr($digest, 0, 16);
        }

        $candidate = self::sanitize($prefix.'-of.'.$label.$suffix);
        $owners = $this->genericOwners[$document] ?? [];
        $name = $candidate;
        if (isset($owners[$name]) && $owners[$name] !== $identity) {
            $name = $candidate.'.'.substr($identity, 0, 16);
            if (isset($owners[$name]) && $owners[$name] !== $identity) {
                $name = $candidate.'.'.$identity;
            }
        }
        $owners[$name] = $identity;
        $names[$context] = $name;
        $this->genericOwners[$document] = $owners;
        $this->genericNames[$document] = $names;

        return $name;
    }

    private function argumentLabel(Type $type, SchemaDocument $document): ?string
    {
        if ($type instanceof BuiltinType) {
            return $type->getTypeIdentifier()->value;
        }

        if ($type instanceof ObjectType) {
            return $this->previewShortName($type->getClassName(), $document);
        }

        if ($type instanceof CollectionType && !$type instanceof ArrayShapeType && $type->isList()) {
            $label = 'list';
            $arguments = [$type->getCollectionValueType()];
        } elseif ($type instanceof GenericType) {
            if (null === $label = $this->argumentLabel($type->getWrappedType(), $document)) {
                return null;
            }
            $arguments = $type->getVariableTypes();
        } else {
            return null;
        }

        foreach ($arguments as $argument) {
            if (null === $argumentLabel = $this->argumentLabel($argument, $document)) {
                return null;
            }
            $label .= '_'.$argumentLabel;
            if (\strlen($label) > 128) {
                return null;
            }
        }

        return $label;
    }

    /**
     * @param class-string $class
     */
    private function previewShortName(string $class, SchemaDocument $document): string
    {
        $owners = $this->owners[$document] ?? [];
        $segments = explode('\\', $class);
        for ($length = 1, $count = \count($segments); $length <= $count; ++$length) {
            $name = implode(self::GLUE, \array_slice($segments, -$length));
            if (!isset($owners[$name]) || $owners[$name] === $class) {
                return $name;
            }
        }

        return implode(self::GLUE, $segments);
    }

    private static function sanitize(string $name): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9.\-_]/', self::GLUE, $name);
    }

    /**
     * @param class-string $class
     */
    private function claim(string $name, string $class, SchemaDocument $document): ?string
    {
        $owners = $this->owners[$document] ?? [];
        $owner = $owners[$name] ??= $class;
        $this->owners[$document] = $owners;

        return $owner === $class ? $name : null;
    }

    /**
     * @param class-string $class
     */
    private function claimShortName(string $class, SchemaDocument $document): string
    {
        $segments = explode('\\', $class);

        for ($length = 1, $count = \count($segments); $length <= $count; ++$length) {
            if (null !== $name = $this->claim(implode(self::GLUE, \array_slice($segments, -$length)), $class, $document)) {
                return $name;
            }
        }

        return implode(self::GLUE, $segments);
    }

    /**
     * @param array<int|string, mixed> $attributes
     */
    private static function attributesToString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if (\is_array($value)) {
                foreach (explode('_', self::attributesToString($value)) as $child) {
                    $parts[] = $key.self::GLUE.$child;
                }
            } else {
                $parts[] = \is_string($key) ? $key : $value;
            }
        }

        return implode('_', $parts);
    }
}
