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
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\JsonSchema\Attribute\JsonSchemaConstraint;
use Symfony\Component\JsonSchema\ClassSchemaResolver\ClassSchemaResolverInterface;
use Symfony\Component\JsonSchema\ClassSchemaResolver\NativeClassSchemaResolver;
use Symfony\Component\JsonSchema\DefinitionPolicy\DefinitionPolicyInterface;
use Symfony\Component\JsonSchema\DefinitionPolicy\ShortNameDefinitionPolicy;
use Symfony\Component\JsonSchema\DefinitionProcessor\DefinitionProcessorInterface;
use Symfony\Component\JsonSchema\Exception\InvalidArgumentException;
use Symfony\Component\JsonSchema\Exception\LogicException;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\AttributePropertySchemaEnricher;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchema;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\PropertySchemaEnricherInterface;
use Symfony\Component\JsonSchema\PropertySchemaEnricher\ValidatorPropertySchemaEnricher;
use Symfony\Component\JsonSchema\PropertySchemaProvider\PropertySchemaProviderInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\Extractor\SerializerExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
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
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;
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

    private readonly ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver;

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
        private readonly ?ClassMetadataFactoryInterface $classMetadataFactory = null,
        ?ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        private ?TypeResolverInterface $typeResolver = null,
    ) {
        $this->classDiscriminatorResolver = $classDiscriminatorResolver ?? ($classMetadataFactory ? new ClassDiscriminatorFromClassMetadata($classMetadataFactory) : null);
    }

    /**
     * Creates a generator that uses the available components, with additional extensions.
     *
     * The given resolvers and providers run before the built-in ones, the given enrichers and processors after them.
     *
     * @param iterable<ClassSchemaResolverInterface>    $classSchemaResolvers
     * @param iterable<PropertySchemaProviderInterface> $propertySchemaProviders
     * @param iterable<PropertySchemaEnricherInterface> $propertySchemaEnrichers
     * @param iterable<DefinitionProcessorInterface>    $definitionProcessors
     */
    public static function create(?DefinitionPolicyInterface $definitionPolicy = null, iterable $classSchemaResolvers = [], iterable $propertySchemaProviders = [], iterable $propertySchemaEnrichers = [], iterable $definitionProcessors = []): self
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

        $nameConverter = $classMetadataFactory = null;
        if (class_exists(AttributeLoader::class)) {
            $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
            array_unshift($listExtractors, new SerializerExtractor($classMetadataFactory));
            $nameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        }

        $propertyInfoExtractor = new PropertyInfoExtractor($listExtractors, $typeExtractors, $descriptionExtractors, [$reflectionExtractor], [$reflectionExtractor]);

        $builtInEnrichers = [];
        if (class_exists(Validation::class)) {
            $builtInEnrichers[] = new ValidatorPropertySchemaEnricher(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
        }
        $builtInEnrichers[] = new AttributePropertySchemaEnricher();

        return new self($propertyInfoExtractor, $definitionPolicy ?? new ShortNameDefinitionPolicy(), [...self::toList($classSchemaResolvers), new NativeClassSchemaResolver([], $classMetadataFactory)], self::toList($propertySchemaProviders), [...$builtInEnrichers, ...self::toList($propertySchemaEnrichers)], self::toList($definitionProcessors), $nameConverter, $classMetadataFactory);
    }

    public function generate(Type $type, Configuration $config = new Configuration(), ?SchemaDocument $document = null): Schema
    {
        $document ??= new SchemaDocument();

        return $document->createSchema($this->buildRoot($document, fn (): array => $this->buildTypeSchema($type, $config, null, $document)), $config->dialect);
    }

    public function generateForParameters(array $parameters, Configuration $config = new Configuration(), ?SchemaDocument $document = null): Schema
    {
        $document ??= new SchemaDocument();

        return $document->createSchema($this->buildRoot($document, fn (): array => $this->buildParametersSchema($parameters, $config, $document)), $config->dialect);
    }

    /**
     * @param \Closure(): array<string, mixed> $build
     *
     * @return array<string, mixed>
     */
    private function buildRoot(SchemaDocument $document, \Closure $build): array
    {
        $previousDescriber = $document->setDescriber(fn (Type $type, Configuration $config, ?DefinitionParent $parent): array => $this->buildTypeSchema($type, $config, $parent, $document));

        try {
            return $build();
        } finally {
            $document->setDescriber($previousDescriber);
        }
    }

    /**
     * @param list<\ReflectionParameter> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildParametersSchema(array $parameters, Configuration $config, SchemaDocument $document): array
    {
        $schema = ['type' => 'object'];
        $descriptions = [];

        foreach ($parameters as $parameter) {
            try {
                $type = ($this->typeResolver ??= TypeResolver::create())->resolve($parameter);
            } catch (UnsupportedException) {
                $type = null;
            }

            if (null !== $type && !self::isRepresentable($type)) {
                continue;
            }

            $class = $parameter->getDeclaringClass()?->name;
            $parameterSchema = null === $type ? [] : $this->buildTypeSchema($parameter->isVariadic() ? Type::list($type) : $type, $config, null !== $class ? new DefinitionParent($class, $parameter->name, $config) : null, $document);

            if ($parameter->isDefaultValueAvailable() && self::canDescribeDefaultValue($parameter->getType()) && null !== $default = self::normalizeDefaultValue($parameter->getDefaultValue(), $parameterSchema)) {
                $parameterSchema['default'] = $default;
            }

            $function = $parameter->getDeclaringFunction();
            $descriptions[$key = $class.'::'.$function->name] ??= self::getParameterDescriptions($function);

            if (isset($descriptions[$key][$parameter->name])) {
                $parameterSchema['description'] = $descriptions[$key][$parameter->name];
            }

            $required = !$parameter->isOptional();
            foreach ($parameter->getAttributes(JsonSchemaConstraint::class) as $attribute) {
                $constraint = $attribute->newInstance();
                $parameterSchema = AttributePropertySchemaEnricher::applyConstraint($constraint, $parameterSchema, $config->dialect);
                $required = $constraint->required ?? $required;
            }

            $schema['properties'][$parameter->name] = self::wrapReferenceSiblings($parameterSchema, $config->dialect);

            if ($required) {
                $schema['required'][] = $parameter->name;
            }
        }

        if (!$config->allowExtraAttributes) {
            $schema['additionalProperties'] = false;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTypeSchema(Type $type, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        return match (true) {
            $type instanceof UnionType => $this->buildUnionSchema($type, $config, $parent, $document),
            $type instanceof IntersectionType => $this->buildIntersectionSchema($type, $config, $parent, $document),
            $type instanceof ArrayShapeType => $this->buildArrayShapeSchema($type, $config, $parent, $document),
            $type instanceof CollectionType => $this->buildCollectionSchema($type, $config, $parent, $document),
            $type instanceof ObjectShapeType => $this->buildShapeSchema($type->getShape(), $config, $parent, $document),
            $type instanceof GenericType, $type instanceof TemplateType => $this->buildTypeSchema($type->getWrappedType(), $config, $parent, $document),
            $type instanceof ObjectType => $this->buildObjectSchema($type->getClassName(), $config, $parent, $document),
            $type instanceof BuiltinType => $this->buildBuiltinSchema($type, $config->dialect),
            default => throw new InvalidArgumentException(\sprintf('Cannot generate a JSON Schema for type "%s".', $type)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIntersectionSchema(IntersectionType $type, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $schemas = [];

        foreach ($type->getTypes() as $t) {
            $schemas[] = $this->buildTypeSchema($t, $config, $parent, $document);
        }

        return ['allOf' => $schemas];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUnionSchema(UnionType $type, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
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

            $schemas[] = $this->buildTypeSchema($t, $config, $parent, $document);
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
     * @return array<string, mixed>
     */
    private function buildCollectionSchema(CollectionType $type, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $valueSchema = $this->buildTypeSchema($type->getCollectionValueType(), $config, $parent, $document);
        $keyType = $type->getCollectionKeyType();

        if (!$type->isList() && $keyType instanceof BuiltinType && TypeIdentifier::STRING === $keyType->getTypeIdentifier()) {
            return ['type' => 'object', 'additionalProperties' => $valueSchema];
        }

        return ['type' => 'array', 'items' => $valueSchema];
    }

    /**
     * List-shaped arrays are encoded as JSON arrays, other shapes as JSON objects.
     *
     * @return array<string, mixed>
     */
    private function buildArrayShapeSchema(ArrayShapeType $type, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $shape = $type->getShape();
        $extraValueType = $type->getExtraValueType();

        if (!array_is_list($shape) || !($type->getExtraKeyType()?->isIdentifiedBy(TypeIdentifier::INT) ?? true)) {
            return $this->buildShapeSchema($shape, $config, $parent, $document) + ['additionalProperties' => null === $extraValueType ? false : $this->buildTypeSchema($extraValueType, $config, $parent, $document)];
        }

        $valueType = null === $extraValueType ? $type->getCollectionValueType() : CollectionType::mergeCollectionValueTypes([$type->getCollectionValueType(), $extraValueType]);
        $schema = ['type' => 'array', 'items' => $this->buildTypeSchema($valueType, $config, $parent, $document)];

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
     *
     * @return array<string, mixed>
     */
    private function buildShapeSchema(array $shape, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $schema = ['type' => 'object'];

        foreach ($shape as $key => ['type' => $type, 'optional' => $optional]) {
            $schema['properties'][(string) $key] = $this->buildTypeSchema($type, $config, $parent, $document);

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
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function buildObjectSchema(string $class, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document, bool $resolveDiscriminator = true): array
    {
        foreach ($this->classSchemaResolvers as $resolver) {
            if (null !== $schema = $resolver->resolve($class, $config, $document, $parent)) {
                return $schema;
            }
        }

        if ($resolveDiscriminator && null !== $mapping = $this->classDiscriminatorResolver?->getMappingForClass($class)) {
            return $this->buildDiscriminatedSchema($class, $mapping, $config, $parent, $document);
        }

        $name = $this->definitionPolicy->nameFor($class, $config, $document, $parent);
        $fingerprint = serialize([$class, $config->groups, $config->attributes, $config->ignoredAttributes, $config->allowExtraAttributes, $config->getValidationGroupNames(), $config->format, $config->direction]);

        if (null === $reservation = $document->reserve($name, $class, $fingerprint, $config->dialect)) {
            $definition = $this->buildDefinition($class, $config, $parent, $document);

            foreach ($this->definitionProcessors as $processor) {
                $definition = $processor->process($definition, $class, $config, $document, $parent);
            }

            $document->define($name, $definition);
        } elseif ($reservation[1] !== $fingerprint) {
            throw new LogicException(\sprintf('Definition "%s" already describes class "%s" with a different configuration, it cannot also describe class "%s", "%s" must return distinct names for distinct shapes.', $name, $reservation[0], $class, $this->definitionPolicy::class));
        }

        return ['$ref' => $config->dialect->refPath.$name];
    }

    /**
     * Describes a class that declares a discriminator map: the value is one of the mapped classes, and the type property tells which one.
     *
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function buildDiscriminatedSchema(string $class, ClassDiscriminatorMapping $mapping, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $typeProperty = $this->getSerializedName($mapping->getTypeProperty(), $class, $config);
        $typesByClass = [];

        foreach ($mapping->getTypesMapping() as $type => $mappedClass) {
            $typesByClass[$mappedClass][] = (string) $type;
        }

        $schemas = [];
        foreach ($typesByClass as $mappedClass => $types) {
            $schema = $this->buildObjectSchema($mappedClass, $config, $parent, $document, $mappedClass !== $class);

            if (Direction::Output === $config->direction || !\in_array($mapping->getDefaultType(), $types, true)) {
                $schema = self::wrapReferenceSiblings([...$schema, 'required' => [...$schema['required'] ?? [], $typeProperty]], $config->dialect);
            }

            $schemas[] = $schema;
        }

        return 1 === \count($schemas) ? $schemas[0] : ['anyOf' => $schemas];
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function buildDefinition(string $class, Configuration $config, ?DefinitionParent $parent, SchemaDocument $document): array
    {
        $context = [...$config->context, 'serializer_groups' => $config->groups ?: null];

        $definition = ['type' => 'object'];

        foreach ($this->propertyInfoExtractor->getProperties($class, $context) ?? [] as $property) {
            if (\in_array($property, $config->ignoredAttributes, true) || !self::isAllowedAttribute($property, $config->attributes)) {
                continue;
            }

            $readable = $this->propertyInfoExtractor->isReadable($class, $property, $context) ?? false;
            $writable = ($this->propertyInfoExtractor->isWritable($class, $property, $context) ?? false)
                || ($this->propertyInfoExtractor instanceof PropertyInitializableExtractorInterface && ($this->propertyInfoExtractor->isInitializable($class, $property, $context) ?? false));

            if ((!$readable && !$writable && !$config->groups) || (Direction::Input === $config->direction && !$writable) || (Direction::Output === $config->direction && !$readable)) {
                continue;
            }

            $type = $this->propertyInfoExtractor->getType($class, $property, $context);
            $schema = $this->provideSchema($class, $property, $config, $document);

            if (null === $schema) {
                if (null !== $type && !self::isRepresentable($type)) {
                    continue;
                }

                $schema = null !== $type ? $this->buildTypeSchema($type, $this->createChildConfiguration($config, $property), new DefinitionParent($class, $property, $config, $parent), $document) : [];
            }

            if (null !== $default = self::normalizeDefaultValue(self::getDefaultValue($class, $property), $schema)) {
                $schema['default'] = $default;
            }

            if (null === $config->direction && $readable && !$writable) {
                $schema['readOnly'] = true;
            }

            if (null === $config->direction && $writable && !$readable) {
                $schema['writeOnly'] = true;
            }

            if (!isset($schema['description']) && '' !== ($description = $this->propertyInfoExtractor->getShortDescription($class, $property, $context) ?? '')) {
                $schema['description'] = $description;
            }

            $required = Direction::Output === $config->direction ? self::isAlwaysInitialized($class, $property) : \in_array($property, $this->getMandatoryConstructorArguments($class), true);
            $propertySchema = new PropertySchema($class, $property, $type, $schema, $required);
            foreach ($this->propertySchemaEnrichers as $enricher) {
                $propertySchema = $enricher->enrich($propertySchema, $config, $document);
            }

            $path = $this->getSerializedPath($class, $property) ?? [$this->getSerializedName($property, $class, $config)];
            self::setPropertySchema($definition, $path, self::wrapReferenceSiblings($propertySchema->schema, $config->dialect), $propertySchema->required);
        }

        $typeProperties = [];
        foreach ($this->getDiscriminatorTypes($class) as $typeProperty => $types) {
            $typeProperties[$this->getSerializedName($typeProperty, $class, $config)] = ['type' => 'string', ...(1 === \count($types) ? $config->dialect->const($types[0]) : ['enum' => $types])];
        }

        if ($typeProperties) {
            foreach ($typeProperties as $name => $typeSchema) {
                if (isset($definition['properties'][$name])) {
                    $definition['properties'][$name] = [...$definition['properties'][$name], ...$typeSchema];
                    unset($typeProperties[$name]);
                }
            }

            if (Direction::Output === $config->direction) {
                $definition['required'] = array_values(array_unique([...array_keys($typeProperties), ...$definition['required'] ?? []]));
            }

            $definition['properties'] = $typeProperties + ($definition['properties'] ?? []);
        }

        if (!$config->allowExtraAttributes) {
            $definition['additionalProperties'] = false;
        }

        return $definition;
    }

    /**
     * @param class-string $class
     */
    private function getSerializedName(string $property, string $class, Configuration $config): string
    {
        if (null === $this->nameConverter) {
            return $property;
        }

        return $this->nameConverter->normalize($property, $class, $config->format, array_filter(['groups' => $config->groups, 'attributes' => $config->attributes], static fn (?array $value): bool => null !== $value && [] !== $value));
    }

    /**
     * @param class-string $class
     *
     * @return list<string>|null
     */
    private function getSerializedPath(string $class, string $property): ?array
    {
        if (null === $this->classMetadataFactory || !$this->classMetadataFactory->hasMetadataFor($class)) {
            return null;
        }

        return ($this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata()[$property] ?? null)?->getSerializedPath()?->getElements();
    }

    /**
     * Returns the values the type property of each discriminator map the class takes part in can have.
     *
     * @param class-string $class
     *
     * @return array<string, list<string>>
     */
    private function getDiscriminatorTypes(string $class): array
    {
        if (null === $this->classDiscriminatorResolver || (!class_exists($class) && !interface_exists($class))) {
            return [];
        }

        $types = [];
        foreach ([$class => $class] + class_parents($class) + class_implements($class) as $mappingClass) {
            if (null === ($mapping = $this->classDiscriminatorResolver->getMappingForClass($mappingClass)) || null === $type = $mapping->getMappedObjectType($class)) {
                continue;
            }

            $types[$mapping->getTypeProperty()] ??= array_map('strval', array_keys($mapping->getTypesMapping(), $mapping->getClassForType($type), true));
        }

        return $types;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>|null
     */
    private function provideSchema(string $class, string $property, Configuration $config, SchemaDocument $document): ?array
    {
        foreach ($this->propertySchemaProviders as $provider) {
            if (null !== $schema = $provider->provide($class, $property, $config, $document)) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Sets the schema of a property, nested in objects when the property is serialized at a path.
     *
     * @param array<string, mixed> $object
     * @param list<string>         $path
     * @param array<string, mixed> $schema
     */
    private static function setPropertySchema(array &$object, array $path, array $schema, bool $required): void
    {
        $key = array_shift($path);

        if ($path) {
            $object['properties'][$key] ??= ['type' => 'object'];
            self::setPropertySchema($object['properties'][$key], $path, $schema, $required);
        } else {
            $object['properties'][$key] = $schema;
        }

        if ($required && !\in_array($key, $object['required'] ?? [], true)) {
            $object['required'][] = $key;
        }
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

    /**
     * @template T
     *
     * @param iterable<T> $items
     *
     * @return list<T>
     */
    private static function toList(iterable $items): array
    {
        return \is_array($items) ? array_values($items) : iterator_to_array($items, false);
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

        return new Configuration($config->dialect, $config->groups, $attributes, $config->ignoredAttributes, $config->allowExtraAttributes, $config->validationGroups, $config->definitionName, $config->definitionPrefix, $config->format, $config->direction, $config->context);
    }

    /**
     * @param array<int|string, mixed>|null $attributes
     */
    private static function isAllowedAttribute(string $property, ?array $attributes): bool
    {
        return null === $attributes || \in_array($property, $attributes, true) || \array_key_exists($property, $attributes);
    }

    /**
     * The serializer cannot instantiate the class without these arguments: it passes null to nullable ones and bypasses non-public constructors.
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
            array_filter($constructor->getParameters(), static fn (\ReflectionParameter $parameter): bool => !$parameter->isDefaultValueAvailable() && !$parameter->isVariadic() && !$parameter->getType()?->allowsNull()),
        )) : [];
    }

    /**
     * Tells whether a property is always part of the normalized data: accessors and properties that have a default value or are set by the constructor.
     *
     * @param class-string $class
     */
    private static function isAlwaysInitialized(string $class, string $property): bool
    {
        if (!property_exists($class, $property)) {
            return true;
        }

        $reflection = new \ReflectionProperty($class, $property);

        if ($reflection->hasDefaultValue() || $reflection->isPromoted()) {
            return true;
        }

        return array_any((new \ReflectionClass($class))->getConstructor()?->getParameters() ?? [], static fn (\ReflectionParameter $parameter): bool => $parameter->name === $property);
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
                    return $parameter->isDefaultValueAvailable() && self::canDescribeDefaultValue($parameter->getType()) ? $parameter->getDefaultValue() : null;
                }
            }
        }

        return $reflection->hasDefaultValue() ? $reflection->getDefaultValue() : null;
    }

    /**
     * @return array<string, string>
     */
    private static function getParameterDescriptions(\ReflectionFunctionAbstract $function): array
    {
        if (false === ($docComment = $function->getDocComment()) || !class_exists(ParserConfig::class)) {
            return [];
        }

        $parserConfig = new ParserConfig([]);
        $parser = new PhpDocParser($parserConfig, new TypeParser($parserConfig, new ConstExprParser($parserConfig)), new ConstExprParser($parserConfig));
        $lexer = new Lexer($parserConfig);
        $phpDocNode = $parser->parse(new TokenIterator($lexer->tokenize($docComment)));
        $descriptions = [];

        foreach ([...$phpDocNode->getParamTagValues(), ...$phpDocNode->getTypelessParamTagValues()] as $tag) {
            if ('' !== $description = trim((string) preg_replace('/\s+/', ' ', $tag->description))) {
                $descriptions[substr($tag->parameterName, 1)] = $description;
            }
        }

        return $descriptions;
    }

    /**
     * Tells whether a default value of this type can be described.
     *
     * Objects other than enums cannot, and getting them would run their constructor.
     */
    private static function canDescribeDefaultValue(?\ReflectionType $type): bool
    {
        return match (true) {
            null === $type => true,
            $type instanceof \ReflectionNamedType => $type->isBuiltin() ? 'object' !== $type->getName() : enum_exists($type->getName()),
            $type instanceof \ReflectionUnionType => array_any($type->getTypes(), self::canDescribeDefaultValue(...)),
            default => false,
        };
    }

    /**
     * An empty array is a JSON array for lists and a JSON object for maps, so it is only described when the schema tells which one.
     *
     * @param array<string, mixed> $schema
     */
    private static function normalizeDefaultValue(mixed $value, array $schema): mixed
    {
        if ([] === $value) {
            $types = (array) ($schema['type'] ?? []);

            return match (true) {
                \in_array('array', $types, true) => [],
                \in_array('object', $types, true) => new \ArrayObject(),
                default => null,
            };
        }

        return self::isEncodableDefaultValue($value) ? self::normalizeEnums($value) : null;
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
