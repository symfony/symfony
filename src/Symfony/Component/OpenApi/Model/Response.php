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
class Response implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Parameter|Reference>|null $headers
     * @param array<string, Link|Reference>|null      $links
     */
    public function __construct(
        private readonly string $description,
        private readonly ?array $headers = null,
        private readonly ?array $content = null,
        private readonly ?array $links = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, Parameter|Reference>|null
     */
    public function getHeaders(): ?array
    {
        return $this->headers;
    }

    public function getContent(): ?array
    {
        return $this->content;
    }

    /**
     * @return array<string, Link|Reference>|null
     */
    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->getDescription(),
            'headers' => $this->normalizeCollection($this->getHeaders()),
            'content' => $this->normalizeCollection($this->getContent()),
            'links' => $this->normalizeCollection($this->getLinks()),
        ] + $this->getSpecificationExtensions());
    }
}
