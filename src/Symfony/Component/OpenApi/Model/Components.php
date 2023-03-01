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
class Components implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, Schema|Reference>|null          $schemas
     * @param array<string, Response|Reference>|null        $responses
     * @param array<string, Parameter|Reference>|null       $parameters
     * @param array<string, Example|Reference>|null         $examples
     * @param array<string, RequestBody|Reference>|null     $requestBodies
     * @param array<string, SecurityScheme|Reference>|null  $securitySchemes
     * @param array<string, Link|Reference>|null            $links
     * @param array<string, CallbackRequest|Reference>|null $callbacks
     * @param array<string, PathItem|Reference>|null        $pathItems
     */
    public function __construct(
        private readonly ?array $schemas = null,
        private readonly ?array $responses = null,
        private readonly ?array $parameters = null,
        private readonly ?array $examples = null,
        private readonly ?array $requestBodies = null,
        private readonly ?array $securitySchemes = null,
        private readonly ?array $links = null,
        private readonly ?array $callbacks = null,
        private readonly ?array $pathItems = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    /**
     * @return array<string, Schema|Reference>|null
     */
    public function getSchemas(): ?array
    {
        return $this->schemas;
    }

    /**
     * @return array<string, Response|Reference>|null
     */
    public function getResponses(): ?array
    {
        return $this->responses;
    }

    /**
     * @return array<string, Parameter|Reference>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, Example|Reference>|null
     */
    public function getExamples(): ?array
    {
        return $this->examples;
    }

    /**
     * @return array<string, RequestBody|Reference>|null
     */
    public function getRequestBodies(): ?array
    {
        return $this->requestBodies;
    }

    /**
     * @return array<string, SecurityScheme|Reference>|null
     */
    public function getSecuritySchemes(): ?array
    {
        return $this->securitySchemes;
    }

    /**
     * @return array<string, Link|Reference>|null
     */
    public function getLinks(): ?array
    {
        return $this->links;
    }

    /**
     * @return array<string, CallbackRequest|Reference>|null
     */
    public function getCallbacks(): ?array
    {
        return $this->callbacks;
    }

    /**
     * @return array<string, PathItem|Reference>|null
     */
    public function getPathItems(): ?array
    {
        return $this->pathItems;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'schemas' => $this->normalizeCollection($this->getSchemas()),
            'responses' => $this->normalizeCollection($this->getResponses()),
            'parameters' => $this->normalizeCollection($this->getParameters()),
            'examples' => $this->normalizeCollection($this->getExamples()),
            'requestBodies' => $this->normalizeCollection($this->getRequestBodies()),
            'securitySchemes' => $this->normalizeCollection($this->getSecuritySchemes()),
            'links' => $this->normalizeCollection($this->getLinks()),
            'callbacks' => $this->normalizeCollection($this->getCallbacks()),
            'pathItems' => $this->normalizeCollection($this->getPathItems()), // ??
        ] + $this->getSpecificationExtensions());
    }
}
