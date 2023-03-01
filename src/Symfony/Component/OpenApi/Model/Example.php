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
class Example implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly ?string $summary = null,
        private readonly ?string $description = null,
        private readonly mixed $value = null,
        private readonly ?string $externalValue = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getExternalValue(): ?string
    {
        return $this->externalValue;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'summary' => $this->getSummary(),
            'description' => $this->getDescription(),
            'value' => $this->normalizeCollection($this->getValue()),
            'externalValue' => $this->getExternalValue(),
        ] + $this->getSpecificationExtensions());
    }
}
