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
class PathItem implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param Server[]|null                   $servers
     * @param array<Parameter|Reference>|null $parameters
     */
    public function __construct(
        private readonly ?string $ref = null,
        private readonly ?string $summary = null,
        private readonly ?string $description = null,
        private readonly ?Operation $get = null,
        private readonly ?Operation $put = null,
        private readonly ?Operation $post = null,
        private readonly ?Operation $patch = null,
        private readonly ?Operation $delete = null,
        private readonly ?Operation $head = null,
        private readonly ?Operation $options = null,
        private readonly ?Operation $trace = null,
        private readonly ?array $servers = null,
        private readonly ?array $parameters = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getRef(): ?string
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

    public function getGet(): ?Operation
    {
        return $this->get;
    }

    public function getPut(): ?Operation
    {
        return $this->put;
    }

    public function getPost(): ?Operation
    {
        return $this->post;
    }

    public function getPatch(): ?Operation
    {
        return $this->patch;
    }

    public function getDelete(): ?Operation
    {
        return $this->delete;
    }

    public function getHead(): ?Operation
    {
        return $this->head;
    }

    public function getOptions(): ?Operation
    {
        return $this->options;
    }

    public function getTrace(): ?Operation
    {
        return $this->trace;
    }

    /**
     * @return Server[]|null
     */
    public function getServers(): ?array
    {
        return $this->servers;
    }

    /**
     * @return array<Parameter|Reference>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            '$ref' => $this->getRef(),
            'summary' => $this->getSummary(),
            'description' => $this->getDescription(),
            'get' => $this->getGet()?->toArray(),
            'put' => $this->getPut()?->toArray(),
            'post' => $this->getPost()?->toArray(),
            'delete' => $this->getDelete()?->toArray(),
            'options' => $this->getOptions()?->toArray(),
            'head' => $this->getHead()?->toArray(),
            'patch' => $this->getPatch()?->toArray(),
            'trace' => $this->getTrace()?->toArray(),
            'servers' => $this->normalizeCollection($this->getServers()),
            'parameters' => $this->normalizeCollection($this->getParameters()),
        ] + $this->getSpecificationExtensions());
    }
}
