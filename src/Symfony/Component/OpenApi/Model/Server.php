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
class Server implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, ServerVariable>|null $variables
     */
    public function __construct(
        private readonly string $url,
        private readonly ?string $description = null,
        private readonly ?array $variables = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, ServerVariable>|null
     */
    public function getVariables(): ?array
    {
        return $this->variables;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'url' => $this->getUrl(),
            'description' => $this->getDescription(),
            'variables' => $this->normalizeCollection($this->getVariables()),
        ] + $this->getSpecificationExtensions());
    }
}
