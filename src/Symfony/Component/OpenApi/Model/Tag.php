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
class Tag implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly string $name,
        private readonly ?string $description = null,
        private readonly ?ExternalDocumentation $externalDocs = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getExternalDocs(): ?ExternalDocumentation
    {
        return $this->externalDocs;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'externalDocs' => $this->getExternalDocs()?->toArray(),
        ] + $this->getSpecificationExtensions());
    }
}
