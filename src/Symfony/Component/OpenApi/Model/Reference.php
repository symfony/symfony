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
class Reference implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly string $ref,
        private readonly ?string $summary = null,
        private readonly ?string $description = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            '$ref' => $this->getRef(),
            'summary' => $this->getSummary(), // ??
            'description' => $this->getDescription(), // ??
        ] + $this->getSpecificationExtensions());
    }
}
