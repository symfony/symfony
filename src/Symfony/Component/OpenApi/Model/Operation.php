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
class Operation implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param string[]|null                                 $tags
     * @param array<Parameter|Reference>|null               $parameters
     * @param array<string, CallbackRequest|Reference>|null $callbacks
     * @param SecurityRequirement[]|null                    $security
     * @param Server[]|null                                 $servers
     */
    public function __construct(
        private readonly ?string $operationId = null,
        private readonly ?string $summary = null,
        private readonly ?string $description = null,
        private readonly ?bool $deprecated = null,
        private readonly ?array $tags = null,
        private readonly ?ExternalDocumentation $externalDocs = null,
        private readonly ?array $parameters = null,
        private readonly RequestBody|Reference|null $requestBody = null,
        private readonly ?Responses $responses = null,
        private readonly ?array $callbacks = null,
        private readonly ?array $security = null,
        private readonly ?array $servers = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDeprecated(): ?bool
    {
        return $this->deprecated;
    }

    /**
     * @return string[]|null
     */
    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getExternalDocs(): ?ExternalDocumentation
    {
        return $this->externalDocs;
    }

    /**
     * @return array<Parameter|Reference>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getRequestBody(): RequestBody|Reference|null
    {
        return $this->requestBody;
    }

    public function getResponses(): ?Responses
    {
        return $this->responses;
    }

    /**
     * @return array<string, CallbackRequest|Reference>|null
     */
    public function getCallbacks(): ?array
    {
        return $this->callbacks;
    }

    /**
     * @return SecurityRequirement[]|null
     */
    public function getSecurity(): ?array
    {
        return $this->security;
    }

    /**
     * @return Server[]|null
     */
    public function getServers(): ?array
    {
        return $this->servers;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        $exported = array_filter([
            'tags' => $this->normalizeCollection($this->getTags()),
            'summary' => $this->getSummary(),
            'description' => $this->getDescription(),
            'externalDocs' => $this->externalDocs?->toArray(),
            'operationId' => $this->getOperationId(),
            'parameters' => $this->normalizeCollection($this->getParameters()),
            'requestBody' => $this->getRequestBody()?->toArray(),
            'responses' => $this->getResponses()?->toArray(),
            'callbacks' => $this->normalizeCollection($this->getCallbacks()),
            'deprecated' => $this->getDeprecated(),
            'servers' => $this->normalizeCollection($this->getServers()),
        ] + $this->getSpecificationExtensions());

        if (null !== $this->security) {
            $exportedSecurity = $this->normalizeCollection($this->getSecurity());
            if ($exportedSecurity === [['__NO_SECURITY' => []]]) {
                $exportedSecurity = [];
            }

            $exported += ['security' => $exportedSecurity];
        }

        return $exported;
    }
}
