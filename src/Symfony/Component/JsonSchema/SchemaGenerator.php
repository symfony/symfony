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

use phpDocumentor\Reflection\Types\ContextFactory;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\ClassSchemaResolver\UidClassSchemaResolver;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Enricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\Enricher\DescriptionPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Enricher\PropertySchema;
use Symfony\Component\JsonSchema\Enricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\Enricher\ValidatorPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Exception\CircularReferenceException;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\ObjectShapeType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Validator\Validation;

/**
 * @experimental
 */
final class SchemaGenerator implements SchemaGeneratorInterface
{
    /**
     * @param iterable<ClassSchemaResolverInterface>    $classSchemaResolvers
     * @param iterable<PropertySchemaEnricherInterface> $propertySchemaEnrichers
     * @param iterable<DefinitionProcessorInterface>    $definitionProcessors
     */
    public function __construct(
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly DefinitionPolicyInterface $definitionPolicy = new ShortNameDefinitionPolicy(),
        private readonly iterable $classSchemaResolvers = [],
        private readonly iterable $propertySchemaEnrichers = [],
        private readonly iterable $definitionProcessors = [],
        private readonly ?NameConverterInterface $nameConverter = null,
    ) {
    }

    public static function create(): self
    {
        $reflectionExtractor = new ReflectionExtractor();
        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$reflectionExtractor];
        $descriptionExtractors = [];

        if (class_exists(PhpDocParser::class) && class_exists(ContextFactory::class)) {
            $phpStanExtractor = new PhpStanExtractor();
            array_unshift($typeExtractors, $phpStanExtractor);
            $descriptionExtractors[] = $phpStanExtractor;
        }

        if (class_exists(AttributeLoader::class)) {
            array_unshift($listExtractors, new SerializerExtractor(new ClassMetadataFactory(new AttributeLoader())));
        }

        $propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors, $descriptionExtractors, [$reflectionExtractor], [$reflectionExtractor]);

        $classSchemaResolvers = [new NativeClassSchemaResolver()];
        if (class_exists(AbstractUid::class)) {
            $classSchemaResolvers[] = new UidClassSchemaResolver();
        }

        $propertySchemaEnrichers = [new DescriptionPropertySchemaEnricher($propertyInfoExtractor)];
        if (class_exists(Validation::class)) {
            $propertySchemaEnrichers[] = new ValidatorPropertySchemaEnricher(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        }
        $propertySchemaEnrichers[] = new AttributePropertySchemaEnricher();

        return new self($propertyInfoExtractor, new ShortNameDefinitionPolicy(), $classSchemaResolvers, $propertySchemaEnrichers);
    }

    public function generate(Type $type, Configuration $config = new Configuration()): Schema
    {
        $definitions = [];
        $classes = [];
        $schema = new Schema($this->buildTypeSchema($type, $config, null, $definitions, $classes), $definitions, $config->dialect);

        return match ($config->references) {
            ReferenceStrategy::ByDefinition => $schema,
            ReferenceStrategy::InlineOnCycle => $schema->flatten(),
            ReferenceStrategy::InlineAlways => $this->inlineAlways($schema, $classes),
        };
    }

    /**
     * @param array<string, class-string> $classes
     */
    private function inlineAlways(Schema $schema, array $classes): Schema
    {
        $flattened = $schema->flatten();

        if ($cyclic = array_keys($flattened->getDefinitions())) {
            throw new CircularReferenceException(\sprintf('Cannot inline the schema of "%s" as it references itself, use "%s::InlineOnCycle" or "%s::ByDefinition" instead.', implode('", "', array_map(static fn (string $name): string => $classes[$name], $cyclic)), ReferenceStrategy::class, ReferenceStrategy::class));
        }

        return $flattened;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, class-string>         $classes
     *
     * @return array<string, mixed>
     */
    private function buildTypeSchema(Type $type, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        return match (true) {
            $type instanceof UnionType => $this->buildUnionSchema($type, $config, $parent, $definitions, $classes),
            $type instanceof IntersectionType => ['allOf' => array_map(fn (Type $t): array => $this->buildTypeSchema($t, $config, $parent, $definitions, $classes), $type->getTypes())],
            $type instanceof ArrayShapeType => $this->buildShapeSchema($type->getShape(), $config, $parent, $definitions, $classes) + match (true) {
                $type->isSealed() => ['additionalProperties' => false],
                null !== $type->getExtraValueType() => ['additionalProperties' => $this->buildTypeSchema($type->getExtraValueType(), $config, $parent, $definitions, $classes)],
                default => [],
            },
            $type instanceof CollectionType => $this->buildCollectionSchema($type, $config, $parent, $definitions, $classes),
            $type instanceof ObjectShapeType => $this->buildShapeSchema($type->getShape(), $config, $parent, $definitions, $classes),
            $type instanceof GenericType, $type instanceof TemplateType => $this->buildTypeSchema($type->getWrappedType(), $config, $parent, $definitions, $classes),
            $type instanceof ObjectType => $this->buildObjectSchema($type->getClassName(), $config, $parent, $definitions, $classes),
            $type instanceof BuiltinType => $this->buildBuiltinSchema($type, $config->dialect),
            default => throw new InvalidArgumentException(\sprintf('Cannot generate a JSON Schema for type "%s".', $type)),
        };
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, class-string>         $classes
     *
     * @return array<string, mixed>
     */
    private function buildUnionSchema(UnionType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        $nullable = false;
        $schemas = [];

        foreach ($type->getTypes() as $t) {
            if ($t instanceof BuiltinType && TypeIdentifier::NULL === $t->getTypeIdentifier()) {
                $nullable = true;

                continue;
            }

            $schemas[] = $this->buildTypeSchema($t, $config, $parent, $definitions, $classes);
        }

        $schema = 1 === \count($schemas) ? $schemas[0] : ['anyOf' => $schemas];

        return $nullable ? $this->makeNullable($schema, $config->dialect) : $schema;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function makeNullable(array $schema, Dialect $dialect): array
    {
        if ([] === $schema || DialectKeywords::allowsNull($schema)) {
            return $schema;
        }

        if (\array_key_exists('const', $schema)) {
            $const = $schema['const'];
            unset($schema['const']);
            $schema['enum'] = [$const];
        }

        if (isset($schema['enum']) && NullSyntax::Unsupported !== $dialect->nullSyntax) {
            $schema['enum'][] = null;
        }

        return match ($dialect->nullSyntax) {
            NullSyntax::Unsupported => $schema,
            NullSyntax::NullableFlag => isset($schema['$ref']) ? ['allOf' => [$schema], 'nullable' => true] : [...$schema, 'nullable' => true],
            NullSyntax::Union => match (true) {
                isset($schema['type']) => [...$schema, 'type' => [...(array) $schema['type'], 'null']],
                isset($schema['anyOf']) && 1 === \count($schema) => ['anyOf' => [...$schema['anyOf'], ['type' => 'null']]],
                default => ['anyOf' => [$schema, ['type' => 'null']]],
            },
        };
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, class-string>         $classes
     *
     * @return array<string, mixed>
     */
    private function buildCollectionSchema(CollectionType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        $valueSchema = $this->buildTypeSchema($type->getCollectionValueType(), $config, $parent, $definitions, $classes);
        $keyType = $type->getCollectionKeyType();

        if (!$type->isList() && $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier()) {
            return ['type' => 'object', 'additionalProperties' => $valueSchema];
        }

        return ['type' => 'array', 'items' => $valueSchema];
    }

    /**
     * @param array<array{type: Type, optional?: bool}> $shape
     * @param array<string, array<string, mixed>>       $definitions
     * @param array<string, class-string>               $classes
     *
     * @return array<string, mixed>
     */
    private function buildShapeSchema(array $shape, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        $schema = ['type' => 'object'];

        foreach ($shape as $key => ['type' => $type, 'optional' => $optional]) {
            $schema['properties'][(string) $key] = $this->buildTypeSchema($type, $config, $parent, $definitions, $classes);

            if (!$optional && !$config->partial) {
                $schema['required'][] = (string) $key;
            }
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBuiltinSchema(BuiltinType $type, Dialect $dialect): array
    {
        return match ($type->getTypeIdentifier()) {
            TypeIdentifier::INT => ['type' => 'integer'],
            TypeIdentifier::FLOAT => ['type' => 'number'],
            TypeIdentifier::BOOL => ['type' => 'boolean'],
            TypeIdentifier::TRUE => ['type' => 'boolean', ...DialectKeywords::constant(true, $dialect)],
            TypeIdentifier::FALSE => ['type' => 'boolean', ...DialectKeywords::constant(false, $dialect)],
            TypeIdentifier::STRING => ['type' => 'string'],
            TypeIdentifier::ARRAY, TypeIdentifier::ITERABLE => ['type' => 'array'],
            TypeIdentifier::OBJECT => ['type' => 'object'],
            TypeIdentifier::NULL => ['type' => 'null'],
            TypeIdentifier::MIXED => [],
            default => throw new InvalidArgumentException(\sprintf('Cannot generate a JSON Schema for type "%s".', $type)),
        };
    }

    /**
     * @param class-string                        $class
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, class-string>         $classes
     *
     * @return array<string, mixed>
     */
    private function buildObjectSchema(string $class, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        foreach ($this->classSchemaResolvers as $resolver) {
            if (null !== $schema = $resolver->resolve($class, $config)) {
                return $schema;
            }
        }

        $name = $this->definitionPolicy->nameFor($class, $config, $parent);

        if (!isset($definitions[$name])) {
            $definitions[$name] = [];
            $classes[$name] = $class;
            $definition = $this->buildDefinition($class, $config, $parent, $definitions, $classes);

            foreach ($this->definitionProcessors as $processor) {
                $definition = $processor->process($definition, $class, $config, $parent);
            }

            $definitions[$name] = $definition;
        }

        return ['$ref' => $config->dialect->refPath.$name];
    }

    /**
     * @param class-string                        $class
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, class-string>         $classes
     *
     * @return array<string, mixed>
     */
    private function buildDefinition(string $class, Configuration $config, ?DefinitionParent $parent, array &$definitions, array &$classes): array
    {
        $context = [];
        if ($config->groups) {
            $context['serializer_groups'] = $config->groups;
        }
        if (null !== $config->attributes) {
            $context['serializer_attributes'] = $config->attributes;
        }

        $nameConverterContext = array_filter(['groups' => $config->groups, 'attributes' => $config->attributes], static fn (?array $value): bool => null !== $value && [] !== $value);
        $definition = ['type' => 'object'];
        $required = [];

        foreach ($this->propertyInfoExtractor->getProperties($class, $context) ?? [] as $property) {
            if (\in_array($property, $config->ignoredAttributes, true) || !self::isAllowedAttribute($property, $config->attributes)) {
                continue;
            }

            $readable = $this->propertyInfoExtractor->isReadable($class, $property, $context) ?? false;
            $writable = ($this->propertyInfoExtractor->isWritable($class, $property, $context) ?? false)
                || ($this->propertyInfoExtractor instanceof PropertyInitializableExtractorInterface && ($this->propertyInfoExtractor->isInitializable($class, $property, $context) ?? false));

            if (!match ($config->direction) {
                Direction::Response => $readable,
                Direction::Request => $writable,
                Direction::Bidirectional => $readable || $writable,
            }) {
                continue;
            }

            $type = $this->propertyInfoExtractor->getType($class, $property, $context);
            $schema = null !== $type ? $this->buildTypeSchema($type, $this->createChildConfiguration($config, $property), new DefinitionParent($class, $property, $parent), $definitions, $classes) : [];
            if (null !== $default = self::normalizeDefaultValue(self::getDefaultValue($class, $property))) {
                $schema['default'] = $default;
            }

            if (Direction::Bidirectional === $config->direction) {
                if (!$writable) {
                    $schema['readOnly'] = true;
                }
                if (!$readable) {
                    $schema['writeOnly'] = true;
                }
            }

            $propertySchema = new PropertySchema($class, $property, $type, $schema, false);
            foreach ($this->propertySchemaEnrichers as $enricher) {
                $propertySchema = $enricher->enrich($propertySchema, $config);
            }

            $name = $this->nameConverter?->normalize($property, $class, $config->format, $nameConverterContext) ?? $property;
            $definition['properties'][$name] = $propertySchema->schema;

            if ($propertySchema->required && !$config->partial) {
                $required[] = $name;
            }
        }

        if ($required) {
            $definition['required'] = $required;
        }

        if (!$config->allowExtraAttributes) {
            $definition['additionalProperties'] = false;
        }

        return $definition;
    }

    private function createChildConfiguration(Configuration $config, string $property): Configuration
    {
        $attributes = \is_array($config->attributes[$property] ?? null) ? $config->attributes[$property] : null;

        if (null === $config->definitionPrefix && $attributes === $config->attributes) {
            return $config;
        }

        return $config->with(definitionPrefix: null, attributes: $attributes);
    }

    /**
     * @param array<int|string, mixed>|null $attributes
     */
    private static function isAllowedAttribute(string $property, ?array $attributes): bool
    {
        return null === $attributes || \in_array($property, $attributes, true) || \array_key_exists($property, $attributes);
    }

    private static function getDefaultValue(string $class, string $property): mixed
    {
        if (!property_exists($class, $property)) {
            return null;
        }

        $reflection = new \ReflectionProperty($class, $property);

        if ($reflection->isPromoted()) {
            foreach ($reflection->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->name === $property) {
                    return $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                }
            }
        }

        return $reflection->hasDefaultValue() ? $reflection->getDefaultValue() : null;
    }

    private static function normalizeDefaultValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_object($value), [] === $value => null,
            default => $value,
        };
    }
}
