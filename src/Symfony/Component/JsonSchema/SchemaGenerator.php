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
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionParent;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Enricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\Enricher\PropertySchema;
use Symfony\Component\JsonSchema\Enricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\Enricher\PropertySchemaProviderInterface;
use Symfony\Component\JsonSchema\Enricher\ValidatorPropertySchemaEnricher;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
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
use Symfony\Component\Validator\Validation;

/**
 * @experimental
 */
final class SchemaGenerator implements SchemaGeneratorInterface
{
    /**
     * @var array<class-string, list<string>>
     */
    private array $mandatoryConstructorArguments = [];

    /**
     * Definition name => class and shape-relevant configuration it was built from, for the generate() call in progress.
     *
     * @var array<string, array{class-string, string}>
     */
    private array $definitionInputs = [];

    /**
     * @param iterable<ClassSchemaResolverInterface>    $classSchemaResolvers
     * @param iterable<PropertySchemaProviderInterface> $propertySchemaProviders
     * @param iterable<PropertySchemaEnricherInterface> $propertySchemaEnrichers
     * @param iterable<DefinitionProcessorInterface>    $definitionProcessors
     */
    public function __construct(
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly DefinitionPolicyInterface $definitionPolicy = new ShortNameDefinitionPolicy(),
        private readonly iterable $classSchemaResolvers = [],
        private readonly iterable $propertySchemaProviders = [],
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

        $nameConverter = null;
        if (class_exists(AttributeLoader::class)) {
            $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
            array_unshift($listExtractors, new SerializerExtractor($classMetadataFactory));
            $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        }

        $propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors, $descriptionExtractors, [$reflectionExtractor], [$reflectionExtractor]);

        $propertySchemaEnrichers = [];
        if (class_exists(Validation::class)) {
            $propertySchemaEnrichers[] = new ValidatorPropertySchemaEnricher(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        }
        $propertySchemaEnrichers[] = new AttributePropertySchemaEnricher();

        return new self($propertyInfoExtractor, new ShortNameDefinitionPolicy(), [new NativeClassSchemaResolver()], propertySchemaEnrichers: $propertySchemaEnrichers, nameConverter: $nameConverter);
    }

    public function generate(Type $type, Configuration $config = new Configuration()): Schema
    {
        $definitions = [];
        $previousDefinitionInputs = $this->definitionInputs;
        $this->definitionInputs = [];

        try {
            $schema = $this->buildTypeSchema($type, $config, null, $definitions);
        } finally {
            $this->definitionInputs = $previousDefinitionInputs;
        }

        return new Schema($schema, $definitions, $config->dialect);
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function buildTypeSchema(Type $type, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        return match (true) {
            $type instanceof UnionType => $this->buildUnionSchema($type, $config, $parent, $definitions),
            $type instanceof IntersectionType => $this->buildIntersectionSchema($type, $config, $parent, $definitions),
            $type instanceof ArrayShapeType => $this->buildArrayShapeSchema($type, $config, $parent, $definitions),
            $type instanceof CollectionType => $this->buildCollectionSchema($type, $config, $parent, $definitions),
            $type instanceof ObjectShapeType => $this->buildShapeSchema($type->getShape(), $config, $parent, $definitions),
            $type instanceof GenericType, $type instanceof TemplateType => $this->buildTypeSchema($type->getWrappedType(), $config, $parent, $definitions),
            $type instanceof ObjectType => $this->buildObjectSchema($type->getClassName(), $config, $parent, $definitions),
            $type instanceof BuiltinType => $this->buildBuiltinSchema($type, $config->dialect),
            default => throw new InvalidArgumentException(\sprintf('Cannot generate a JSON Schema for type "%s".', $type)),
        };
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function buildIntersectionSchema(IntersectionType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $schemas = [];

        foreach ($type->getTypes() as $t) {
            $schemas[] = $this->buildTypeSchema($t, $config, $parent, $definitions);
        }

        return ['allOf' => $schemas];
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function buildUnionSchema(UnionType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $nullable = false;
        $schemas = [];
        $dropUnrepresentable = self::isRepresentable($type);

        foreach ($type->getTypes() as $t) {
            if ($t instanceof BuiltinType && TypeIdentifier::NULL === $t->getTypeIdentifier()) {
                $nullable = true;

                continue;
            }

            if ($dropUnrepresentable && !self::isRepresentable($t)) {
                continue;
            }

            $schemas[] = $this->buildTypeSchema($t, $config, $parent, $definitions);
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
                isset($schema['type']) => array_replace($schema, ['type' => [...(array) $schema['type'], 'null']]),
                isset($schema['anyOf']) && 1 === \count($schema) => ['anyOf' => [...$schema['anyOf'], ['type' => 'null']]],
                default => ['anyOf' => [$schema, ['type' => 'null']]],
            },
        };
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function buildCollectionSchema(CollectionType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $valueSchema = $this->buildTypeSchema($type->getCollectionValueType(), $config, $parent, $definitions);
        $keyType = $type->getCollectionKeyType();

        if (!$type->isList() && $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier()) {
            return ['type' => 'object', 'additionalProperties' => $valueSchema];
        }

        return ['type' => 'array', 'items' => $valueSchema];
    }

    /**
     * List-shaped arrays are encoded as JSON arrays, other shapes as JSON objects.
     *
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, mixed>
     */
    private function buildArrayShapeSchema(ArrayShapeType $type, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $shape = $type->getShape();
        $extraValueType = $type->getExtraValueType();

        if (!array_is_list($shape) || !($type->getExtraKeyType()?->isIdentifiedBy(TypeIdentifier::INT) ?? true)) {
            return $this->buildShapeSchema($shape, $config, $parent, $definitions) + ['additionalProperties' => null === $extraValueType ? false : $this->buildTypeSchema($extraValueType, $config, $parent, $definitions)];
        }

        $valueType = null === $extraValueType ? $type->getCollectionValueType() : CollectionType::mergeCollectionValueTypes([$type->getCollectionValueType(), $extraValueType]);
        $schema = ['type' => 'array', 'items' => $this->buildTypeSchema($valueType, $config, $parent, $definitions)];

        if ($minItems = \count(array_filter($shape, static fn (array $element): bool => !$element['optional']))) {
            $schema['minItems'] = $minItems;
        }

        if (null === $extraValueType) {
            $schema['maxItems'] = \count($shape);
        }

        return $schema;
    }

    /**
     * @param array<array{type: Type, optional?: bool}> $shape
     * @param array<string, array<string, mixed>>       $definitions
     *
     * @return array<string, mixed>
     */
    private function buildShapeSchema(array $shape, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $schema = ['type' => 'object'];

        foreach ($shape as $key => ['type' => $type, 'optional' => $optional]) {
            $schema['properties'][(string) $key] = $this->buildTypeSchema($type, $config, $parent, $definitions);

            if (!$optional) {
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
            TypeIdentifier::TRUE => ['type' => 'boolean', ...$dialect->const(true)],
            TypeIdentifier::FALSE => ['type' => 'boolean', ...$dialect->const(false)],
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
     *
     * @return array<string, mixed>
     */
    private function buildObjectSchema(string $class, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        foreach ($this->classSchemaResolvers as $resolver) {
            if (null !== $schema = $resolver->resolve($class, $config, $parent)) {
                return $schema;
            }
        }

        $name = $this->definitionPolicy->nameFor($class, $config, $parent);
        $fingerprint = serialize([$class, $config->groups, $config->attributes, $config->ignoredAttributes, $config->allowExtraAttributes, $config->getValidationGroupNames(), $config->format]);
        [$definedClass, $definedFingerprint] = $this->definitionInputs[$name] ??= [$class, $fingerprint];

        if ($definedFingerprint !== $fingerprint) {
            throw new LogicException(\sprintf('Definition "%s" already describes class "%s" with a different configuration, it cannot also describe class "%s". Set a distinct "definitionName" in the configuration or use a custom "%s".', $name, $definedClass, $class, DefinitionPolicyInterface::class));
        }

        if (!isset($definitions[$name])) {
            $definitions[$name] = [];
            $definition = $this->buildDefinition($class, $config, $parent, $definitions);

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
     *
     * @return array<string, mixed>
     */
    private function buildDefinition(string $class, Configuration $config, ?DefinitionParent $parent, array &$definitions): array
    {
        $context = ['serializer_groups' => $config->groups ?: null];

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

            if (!$readable && !$writable && !$config->groups) {
                continue;
            }

            $type = $this->propertyInfoExtractor->getType($class, $property, $context);
            $schema = $this->provideSchema($class, $property, $config);

            if (null === $schema) {
                if (null !== $type && !self::isRepresentable($type)) {
                    continue;
                }

                $schema = null !== $type ? $this->buildTypeSchema($type, $this->createChildConfiguration($config, $property), new DefinitionParent($class, $property, $config, $parent), $definitions) : [];
            }

            if (null !== $default = self::normalizeDefaultValue(self::getDefaultValue($class, $property))) {
                $schema['default'] = $default;
            }

            if ($readable && !$writable) {
                $schema['readOnly'] = true;
            }

            if ($writable && !$readable) {
                $schema['writeOnly'] = true;
            }

            if (!isset($schema['description']) && '' !== ($description = $this->propertyInfoExtractor->getShortDescription($class, $property, $context) ?? '')) {
                $schema['description'] = $description;
            }

            $propertySchema = new PropertySchema($class, $property, $type, $schema, \in_array($property, $this->getMandatoryConstructorArguments($class), true));
            foreach ($this->propertySchemaEnrichers as $enricher) {
                $propertySchema = $enricher->enrich($propertySchema, $config);
            }

            $name = $this->nameConverter?->normalize($property, $class, $config->format, $nameConverterContext) ?? $property;
            $definition['properties'][$name] = self::wrapReferenceSiblings($propertySchema->schema, $config->dialect);

            if ($propertySchema->required) {
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

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>|null
     */
    private function provideSchema(string $class, string $property, Configuration $config): ?array
    {
        foreach ($this->propertySchemaProviders as $provider) {
            if (null !== $schema = $provider->provide($class, $property, $config)) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function wrapReferenceSiblings(array $schema, Dialect $dialect): array
    {
        if ($dialect->supportsRefSiblings || !isset($schema['$ref']) || 1 === \count($schema)) {
            return $schema;
        }

        return ['allOf' => [['$ref' => $schema['$ref']]], ...array_diff_key($schema, ['$ref' => true])];
    }

    private static function isRepresentable(Type $type): bool
    {
        return match (true) {
            $type instanceof UnionType => array_any($type->getTypes(), static fn (Type $t): bool => !$t->isIdentifiedBy(TypeIdentifier::NULL) && self::isRepresentable($t)),
            $type instanceof BuiltinType => !\in_array($type->getTypeIdentifier(), [TypeIdentifier::CALLABLE, TypeIdentifier::RESOURCE], true),
            default => true,
        };
    }

    private function createChildConfiguration(Configuration $config, string $property): Configuration
    {
        $attributes = \is_array($config->attributes[$property] ?? null) ? $config->attributes[$property] : null;

        if ($attributes === $config->attributes) {
            return $config;
        }

        return new Configuration(
            dialect: $config->dialect,
            groups: $config->groups,
            attributes: $attributes,
            ignoredAttributes: $config->ignoredAttributes,
            allowExtraAttributes: $config->allowExtraAttributes,
            validationGroups: $config->validationGroups,
            definitionName: $config->definitionName,
            definitionPrefix: $config->definitionPrefix,
            format: $config->format,
        );
    }

    /**
     * @param array<int|string, mixed>|null $attributes
     */
    private static function isAllowedAttribute(string $property, ?array $attributes): bool
    {
        return null === $attributes || \in_array($property, $attributes, true) || \array_key_exists($property, $attributes);
    }

    /**
     * The serializer cannot instantiate the class without these arguments; it bypasses non-public constructors.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function getMandatoryConstructorArguments(string $class): array
    {
        if (isset($this->mandatoryConstructorArguments[$class])) {
            return $this->mandatoryConstructorArguments[$class];
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();

        return $this->mandatoryConstructorArguments[$class] = $constructor?->isPublic() ? array_values(array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->name,
            array_filter($constructor->getParameters(), static fn (\ReflectionParameter $parameter): bool => !$parameter->isDefaultValueAvailable() && !$parameter->isVariadic()),
        )) : [];
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
        return [] !== $value && self::isEncodableDefaultValue($value) ? self::normalizeEnums($value) : null;
    }

    private static function isEncodableDefaultValue(mixed $value): bool
    {
        return match (true) {
            $value instanceof \UnitEnum => true,
            \is_array($value) => array_all($value, self::isEncodableDefaultValue(...)),
            default => !\is_object($value),
        };
    }

    private static function normalizeEnums(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_array($value) => array_map(self::normalizeEnums(...), $value),
            default => $value,
        };
    }
}
