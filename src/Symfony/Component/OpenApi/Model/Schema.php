<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Model;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class Schema implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, self|Reference>|null $properties
     */
    public function __construct(
        private readonly ?string $title = null,
        private readonly ?int $multipleOf = null,
        private readonly ?int $maximum = null,
        private readonly ?bool $exclusiveMaximum = null,
        private readonly ?int $minimum = null,
        private readonly ?bool $exclusiveMinimum = null,
        private readonly ?int $maxLength = null,
        private readonly ?int $minLength = null,
        private readonly ?string $pattern = null,
        private readonly ?int $maxItems = null,
        private readonly ?int $minItems = null,
        private readonly ?bool $uniqueItems = null,
        private readonly ?int $maxProperties = null,
        private readonly ?int $minProperties = null,
        private readonly ?array $required = null,
        private readonly ?array $enum = null,
        private readonly ?array $type = null,
        private readonly ?array $allOf = null,
        private readonly ?array $oneOf = null,
        private readonly ?array $anyOf = null,
        private readonly self|Reference|null $not = null,
        private readonly self|Reference|null $items = null,
        private readonly ?array $properties = null,
        private readonly ?string $description = null,
        private readonly ?string $format = null,
        private readonly mixed $default = null,
        private readonly ?bool $readOnly = null,
        private readonly ?bool $writeOnly = null,
        private readonly mixed $example = null,
        private readonly ?bool $deprecated = null,
        private readonly ?Discriminator $discriminator = null,
        private readonly ?Xml $xml = null,
        private readonly ?ExternalDocumentation $externalDocs = null,
        private readonly self|Reference|false|null $additionalProperties = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getMultipleOf(): ?int
    {
        return $this->multipleOf;
    }

    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    public function isExclusiveMaximum(): ?bool
    {
        return $this->exclusiveMaximum;
    }

    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    public function isExclusiveMinimum(): ?bool
    {
        return $this->exclusiveMinimum;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    public function isUniqueItems(): ?bool
    {
        return $this->uniqueItems;
    }

    public function getMaxProperties(): ?int
    {
        return $this->maxProperties;
    }

    public function getMinProperties(): ?int
    {
        return $this->minProperties;
    }

    public function getRequired(): ?array
    {
        return $this->required;
    }

    public function getEnum(): ?array
    {
        return $this->enum;
    }

    public function getType(): ?array
    {
        return $this->type;
    }

    public function getAllOf(): ?array
    {
        return $this->allOf;
    }

    public function getOneOf(): ?array
    {
        return $this->oneOf;
    }

    public function getAnyOf(): ?array
    {
        return $this->anyOf;
    }

    public function getNot(): self|Reference|null
    {
        return $this->not;
    }

    public function getItems(): self|Reference|null
    {
        return $this->items;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isNullable(): ?bool
    {
        return null === $this->type ? null : \in_array('null', $this->type, true);
    }

    public function isReadOnly(): ?bool
    {
        return $this->readOnly;
    }

    public function isWriteOnly(): ?bool
    {
        return $this->writeOnly;
    }

    public function getExample(): mixed
    {
        return $this->example;
    }

    public function isDeprecated(): ?bool
    {
        return $this->deprecated;
    }

    public function getDiscriminator(): ?Discriminator
    {
        return $this->discriminator;
    }

    public function getXml(): ?Xml
    {
        return $this->xml;
    }

    public function getExternalDocs(): ?ExternalDocumentation
    {
        return $this->externalDocs;
    }

    public function getAdditionalProperties(): self|bool|Reference|null
    {
        return $this->additionalProperties;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        $data = [
            'title' => $this->getTitle(),
            'multipleOf' => $this->getMultipleOf(),
            'maximum' => $this->getMaximum(),
            'exclusiveMaximum' => $this->isExclusiveMaximum(),
            'minimum' => $this->getMinimum(),
            'exclusiveMinimum' => $this->isExclusiveMinimum(),
            'maxLength' => $this->getMaxLength(),
            'minLength' => $this->getMinLength(),
            'pattern' => $this->getPattern(),
            'maxItems' => $this->getMaxItems(),
            'minItems' => $this->getMinItems(),
            'uniqueItems' => $this->isUniqueItems(),
            'maxProperties' => $this->getMaxProperties(),
            'minProperties' => $this->getMinProperties(),
            'required' => $this->normalizeCollection($this->getRequired()),
            'enum' => $this->normalizeCollection($this->getEnum()),
            'type' => $this->getType(),
            'allOf' => $this->normalizeCollection($this->getAllOf()),
            'oneOf' => $this->normalizeCollection($this->getOneOf()),
            'anyOf' => $this->normalizeCollection($this->getAnyOf()),
            'not' => $this->getNot()?->toArray(),
            'items' => $this->getItems()?->toArray(),
            'properties' => $this->normalizeCollection($this->getProperties()),
            'description' => $this->getDescription(),
            'format' => $this->getFormat(),
            'default' => $this->getDefault(),
            'discriminator' => $this->getDiscriminator()?->toArray(),
            'readOnly' => $this->isReadOnly(),
            'writeOnly' => $this->isWriteOnly(),
            'xml' => $this->getXml()?->toArray(),
            'externalDocs' => $this->getExternalDocs()?->toArray(),
            'example' => $this->getExample(),
            'deprecated' => $this->isDeprecated(),
        ];

        if (null !== $this->additionalProperties) {
            if (false === $this->additionalProperties) {
                $data['additionalProperties'] = false;
            } else {
                $data['additionalProperties'] = $this->additionalProperties->toArray();
            }
        }

        return array_filter($data + $this->getSpecificationExtensions());
    }
}
