<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator;

use Symfony\Component\OpenApi\Model\Discriminator;
use Symfony\Component\OpenApi\Model\Reference;
use Symfony\Component\OpenApi\Model\Schema;
use Symfony\Component\OpenApi\Model\Xml;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class SchemaConfigurator
{
    use Traits\DeprecatedTrait;
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;
    use Traits\ExternalDocsTrait;

    private const PHP_TO_OPENAPI_TYPE = [
        'null' => 'null',
        'string' => 'string',
        'number' => 'number',
        'float' => 'number',
        'double' => 'number',
        'integer' => 'integer',
        'int' => 'integer',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'array' => 'array',
        'object' => 'object',
    ];

    private ?string $title = null;
    private ?int $multipleOf = null;
    private ?int $maximum = null;
    private ?bool $exclusiveMaximum = null;
    private ?int $minimum = null;
    private ?bool $exclusiveMinimum = null;
    private ?int $maxLength = null;
    private ?int $minLength = null;
    private ?string $pattern = null;
    private ?int $maxItems = null;
    private ?int $minItems = null;
    private ?bool $uniqueItems = null;
    private ?int $maxProperties = null;
    private ?int $minProperties = null;
    private ?array $required = null;
    private ?array $enum = null;
    private ?array $type = null;
    private Schema|Reference|null $not = null;
    private Schema|Reference|null $items = null;
    private ?string $format = null;
    private mixed $default = null;
    private ?bool $readOnly = null;
    private ?bool $writeOnly = null;
    private mixed $example = null;
    private ?Discriminator $discriminator = null;
    private ?Xml $xml = null;
    private Schema|Reference|false|null $additionalProperties = null;

    /**
     * @var array<string, Schema|Reference>|null
     */
    private ?array $properties = null;

    /**
     * @var array<Schema|Reference>|null
     */
    private ?array $allOf = null;

    /**
     * @var array<Schema|Reference>|null
     */
    private ?array $oneOf = null;

    /**
     * @var array<Schema|Reference>|null
     */
    private ?array $anyOf = null;

    public static function createFromDefinition(self|ReferenceConfigurator|string|array $definition = null): self|ReferenceConfigurator
    {
        // Empty schema
        if (!$definition) {
            return new self();
        }

        // Direct schema or reference
        if ($definition instanceof self || $definition instanceof ReferenceConfigurator) {
            return $definition;
        }

        // Array: types list
        if (\is_array($definition)) {
            return (new self())->type($definition);
        }

        $typeDefinition = '?' === $definition[0] ? mb_substr($definition, 1) : $definition;

        // Native type
        if (isset(self::PHP_TO_OPENAPI_TYPE[$typeDefinition])) {
            return (new self())->type($definition);
        }

        // Schema reference
        $reference = new ReferenceConfigurator('#/components/schemas/'.ReferenceConfigurator::normalize($typeDefinition));

        return '?' === $definition[0] ? (new self())->oneOf([$reference, 'null']) : $reference;
    }

    public function build(): Schema
    {
        return new Schema(
            title: $this->title,
            multipleOf: $this->multipleOf,
            maximum: $this->maximum,
            exclusiveMaximum: $this->exclusiveMaximum,
            minimum: $this->minimum,
            exclusiveMinimum: $this->exclusiveMinimum,
            maxLength: $this->maxLength,
            minLength: $this->minLength,
            pattern: $this->pattern,
            maxItems: $this->maxItems,
            minItems: $this->minItems,
            uniqueItems: $this->uniqueItems,
            maxProperties: $this->maxProperties,
            minProperties: $this->minProperties,
            required: $this->required,
            enum: $this->enum,
            type: $this->type,
            allOf: $this->allOf,
            oneOf: $this->oneOf,
            anyOf: $this->anyOf,
            not: $this->not,
            items: $this->items,
            properties: $this->properties,
            description: $this->description,
            format: $this->format,
            default: $this->default,
            readOnly: $this->readOnly,
            writeOnly: $this->writeOnly,
            example: $this->example,
            deprecated: $this->deprecated,
            discriminator: $this->discriminator,
            xml: $this->xml,
            externalDocs: $this->externalDocs,
            additionalProperties: $this->additionalProperties,
            specificationExtensions: $this->specificationExtensions,
        );
    }

    public function property(string $name, self|ReferenceConfigurator|string $definition): static
    {
        $this->properties[$name] = self::createFromDefinition($definition)->build();

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function multipleOf(int $multipleOf): static
    {
        $this->multipleOf = $multipleOf;

        return $this;
    }

    public function maximum(int $maximum): static
    {
        $this->maximum = $maximum;

        return $this;
    }

    public function exclusiveMaximum(bool $exclusiveMaximum): static
    {
        $this->exclusiveMaximum = $exclusiveMaximum;

        return $this;
    }

    public function minimum(int $minimum): static
    {
        $this->minimum = $minimum;

        return $this;
    }

    public function exclusiveMinimum(bool $exclusiveMinimum): static
    {
        $this->exclusiveMinimum = $exclusiveMinimum;

        return $this;
    }

    public function maxLength(int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function minLength(int $minLength): static
    {
        $this->minLength = $minLength;

        return $this;
    }

    public function pattern(string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function maxItems(int $maxItems): static
    {
        $this->maxItems = $maxItems;

        return $this;
    }

    public function minItems(int $minItems): static
    {
        $this->minItems = $minItems;

        return $this;
    }

    public function uniqueItems(bool $uniqueItems): static
    {
        $this->uniqueItems = $uniqueItems;

        return $this;
    }

    public function maxProperties(int $maxProperties): static
    {
        $this->maxProperties = $maxProperties;

        return $this;
    }

    public function minProperties(int $minProperties): static
    {
        $this->minProperties = $minProperties;

        return $this;
    }

    public function required(array $required): static
    {
        $this->required = $required;

        return $this;
    }

    /**
     * @template T of \UnitEnum
     *
     * @param array|class-string<T> $enum
     *
     * @return $this
     */
    public function enum(array|string $enum): static
    {
        if (\is_string($enum)) {
            if (!enum_exists($enum)) {
                throw new \InvalidArgumentException('Parameter #1 $enum of method '.__CLASS__.'::'.__METHOD__.' expects an array or an enum, string given.');
            }

            $enum = $enum::cases();
        }

        $this->enum = $enum;

        return $this;
    }

    public function type(array|string $typeDefinitions): static
    {
        // Only
        if (\is_string($typeDefinitions)) {
            if ('?' === $typeDefinitions[0]) {
                $typeDefinitions = array_unique([mb_substr($typeDefinitions, 1), 'null']);
            } else {
                $typeDefinitions = [$typeDefinitions];
            }
        }

        $types = [];
        foreach ($typeDefinitions as $definition) {
            $definition = mb_strtolower($definition);

            if (!isset(self::PHP_TO_OPENAPI_TYPE[$definition])) {
                throw new \InvalidArgumentException('Type '.$definition.' is not a valid OpenApi type.');
            }

            $types[] = self::PHP_TO_OPENAPI_TYPE[$definition];
        }

        $this->type = $types;

        return $this;
    }

    /**
     * @param array<SchemaConfigurator|ReferenceConfigurator|string> $allOf
     */
    public function allOf(array $allOf): static
    {
        $this->allOf = [];
        foreach ($allOf as $definition) {
            $this->allOf[] = self::createFromDefinition($definition)->build();
        }

        return $this;
    }

    /**
     * @param array<SchemaConfigurator|ReferenceConfigurator|string> $oneOf
     */
    public function oneOf(array $oneOf): static
    {
        $this->oneOf = [];
        foreach ($oneOf as $definition) {
            $this->oneOf[] = self::createFromDefinition($definition)->build();
        }

        return $this;
    }

    /**
     * @param array<SchemaConfigurator|ReferenceConfigurator|string> $anyOf
     */
    public function anyOf(array $anyOf): static
    {
        $this->anyOf = [];
        foreach ($anyOf as $definition) {
            $this->anyOf[] = self::createFromDefinition($definition)->build();
        }

        return $this;
    }

    public function not(self|ReferenceConfigurator|string $not): static
    {
        $this->not = self::createFromDefinition($not)->build();

        return $this;
    }

    public function items(self|ReferenceConfigurator|string $items): static
    {
        $this->items = self::createFromDefinition($items)->build();

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function nullable(bool $nullable): static
    {
        if ($nullable) {
            $this->type = array_unique(array_merge($this->type ?: [], ['null']));
        } else {
            $this->type = array_filter($this->type ?: [], static fn (string $t) => 'null' !== $t);
        }

        return $this;
    }

    public function readOnly(bool $readOnly): static
    {
        $this->readOnly = $readOnly;

        return $this;
    }

    public function writeOnly(bool $writeOnly): static
    {
        $this->writeOnly = $writeOnly;

        return $this;
    }

    public function example(mixed $example): static
    {
        $this->example = $example;

        return $this;
    }

    public function discriminator(string $propertyName, array $mapping = null, array $specificationExtensions = []): static
    {
        $this->discriminator = new Discriminator($propertyName, $mapping, $specificationExtensions);

        return $this;
    }

    public function xml(
        string $name = null,
        string $namespace = null,
        string $prefix = null,
        bool $attribute = null,
        bool $wrapped = null,
        array $specificationExtensions = [],
    ): static {
        $this->xml = new Xml($name, $namespace, $prefix, $attribute, $wrapped, $specificationExtensions);

        return $this;
    }

    public function additionalProperties(self|ReferenceConfigurator|string|false $additionalProperties): static
    {
        if (false === $additionalProperties) {
            $this->additionalProperties = false;
        } else {
            $this->additionalProperties = self::createFromDefinition($additionalProperties)->build();
        }

        return $this;
    }
}
