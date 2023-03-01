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
class Discriminator implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, string>|null $mapping
     */
    public function __construct(
        private readonly string $propertyName,
        private readonly ?array $mapping = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getMapping(): ?array
    {
        return $this->mapping;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'propertyName' => $this->getPropertyName(),
            'mapping' => $this->normalizeCollection($this->getMapping()),
        ] + $this->getSpecificationExtensions());
    }
}
