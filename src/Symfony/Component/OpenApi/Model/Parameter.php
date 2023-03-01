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
class Parameter implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Example|Reference>|null $examples
     * @param array<string, MediaType>|null         $content
     */
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?ParameterIn $in = null,
        private readonly ?string $description = null,
        private readonly ?bool $required = null,
        private readonly ?bool $deprecated = null,
        private readonly ?bool $allowEmptyValue = null,
        private readonly ?string $style = null,
        private readonly ?bool $explode = null,
        private readonly ?bool $allowReserved = null,
        private readonly ?Schema $schema = null,
        private readonly mixed $example = null,
        private readonly ?array $examples = null,
        private readonly ?array $content = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIn(): ?ParameterIn
    {
        return $this->in;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getDeprecated(): ?bool
    {
        return $this->deprecated;
    }

    public function getAllowEmptyValue(): ?bool
    {
        return $this->allowEmptyValue;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    public function getExplode(): ?bool
    {
        return $this->explode;
    }

    public function getAllowReserved(): ?bool
    {
        return $this->allowReserved;
    }

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function getExample(): mixed
    {
        return $this->example;
    }

    /**
     * @return array<string, Example|Reference>|null
     */
    public function getExamples(): ?array
    {
        return $this->examples;
    }

    /**
     * @return array<string, MediaType>|null
     */
    public function getContent(): ?array
    {
        return $this->content;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->getName(),
            'in' => $this->getIn()?->value,
            'description' => $this->getDescription(),
            'required' => $this->getRequired(),
            'deprecated' => $this->getDeprecated(),
            'allowEmptyValue' => $this->getAllowEmptyValue(),
            'style' => $this->getStyle(),
            'explode' => $this->getExplode(),
            'allowReserved' => $this->getAllowReserved(),
            'schema' => $this->getSchema()?->toArray(),
            'example' => $this->getExample(),
            'examples' => $this->normalizeCollection($this->getExamples()),
            'content' => $this->normalizeCollection($this->getContent()),
        ] + $this->getSpecificationExtensions());
    }
}
