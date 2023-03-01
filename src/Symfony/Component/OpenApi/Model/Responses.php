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
class Responses implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Response|Reference>|null $byHttpStatusCode
     */
    public function __construct(
        private readonly Response|Reference|null $default = null,
        private readonly ?array $byHttpStatusCode = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getDefault(): Response|Reference|null
    {
        return $this->default;
    }

    /**
     * @return array<string, Response|Reference>|null
     */
    public function getByHttpStatusCode(): ?array
    {
        return $this->byHttpStatusCode;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'default' => $this->getDefault()?->toArray(),
        ] + $this->normalizeCollection($this->getByHttpStatusCode()) + $this->getSpecificationExtensions());
    }
}
