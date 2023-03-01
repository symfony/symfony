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
class License implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly string $name,
        private readonly ?string $identifier = null,
        private readonly ?string $url = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->getName(),
            'url' => $this->getUrl(),
            'identifier' => $this->getIdentifier(), // ??
        ] + $this->getSpecificationExtensions());
    }
}
