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
class RequestBody implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, MediaType> $content
     */
    public function __construct(
        private readonly array $content,
        private readonly ?string $description = null,
        private readonly ?bool $required = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    /**
     * @return array<string, MediaType>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getRequired(): ?bool
    {
        return $this->required;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->getDescription(),
            'content' => $this->normalizeCollection($this->getContent()),
            'required' => $this->getRequired(),
        ] + $this->getSpecificationExtensions());
    }
}
