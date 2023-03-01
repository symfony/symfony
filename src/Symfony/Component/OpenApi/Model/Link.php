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
class Link implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly ?string $operationRef = null,
        private readonly ?string $operationId = null,
        private readonly ?array $parameters = null,
        private readonly mixed $requestBody = null,
        private readonly ?string $description = null,
        private readonly ?Server $server = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getOperationRef(): ?string
    {
        return $this->operationRef;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getRequestBody(): mixed
    {
        return $this->requestBody;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'operationRef' => $this->getOperationRef(),
            'operationId' => $this->getOperationId(),
            'parameters' => $this->normalizeCollection($this->getParameters()),
            'requestBody' => $this->getRequestBody(),
            'description' => $this->getDescription(),
            'server' => $this->getServer()?->toArray(),
        ] + $this->getSpecificationExtensions());
    }
}
