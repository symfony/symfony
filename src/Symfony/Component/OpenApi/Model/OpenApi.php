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

use Symfony\Component\OpenApi\Configurator\InfoConfigurator;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class OpenApi implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param Server[]|null                          $servers
     * @param array<string, PathItem|Reference>|null $paths
     * @param array<string, PathItem|Reference>|null $webhooks
     * @param SecurityRequirement[]|null             $security
     * @param Tag[]|null                             $tags
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(['3.1.0'])]
        private readonly string $version,

        #[Assert\Valid]
        private readonly Info $info,

        #[Assert\All([new Assert\Type(Server::class), new Assert\Valid()])]
        private readonly ?array $servers = null,

        #[Assert\All([new Assert\Type([PathItem::class, Reference::class]), new Assert\Valid()])]
        private readonly ?array $paths = null,

        #[Assert\All([new Assert\Type([PathItem::class, Reference::class]), new Assert\Valid()])]
        private readonly ?array $webhooks = null,

        #[Assert\Valid]
        private readonly ?Components $components = null,

        #[Assert\All([new Assert\Type(SecurityRequirement::class), new Assert\Valid()])]
        private readonly ?array $security = null,

        #[Assert\All([new Assert\Type(Tag::class), new Assert\Valid()])]
        private readonly ?array $tags = null,

        #[Assert\Valid]
        private readonly ?ExternalDocumentation $externalDocs = null,

        #[Assert\Url]
        private readonly ?string $jsonSchemaDialect = null,

        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getInfo(): Info
    {
        return $this->info;
    }

    /**
     * @return Server[]|null
     */
    public function getServers(): ?array
    {
        return $this->servers;
    }

    /**
     * @return array<string, PathItem>|null
     */
    public function getPaths(): ?array
    {
        return $this->paths;
    }

    /**
     * @return array<string, PathItem>|null
     */
    public function getWebhooks(): ?array
    {
        return $this->webhooks;
    }

    public function getComponents(): ?Components
    {
        return $this->components;
    }

    /**
     * @return SecurityRequirement[]|null
     */
    public function getSecurity(): ?array
    {
        return $this->security;
    }

    /**
     * @return Tag[]|null
     */
    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getExternalDocs(): ?ExternalDocumentation
    {
        return $this->externalDocs;
    }

    public function getJsonSchemaDialect(): ?string
    {
        return $this->jsonSchemaDialect;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        $exported = array_filter([
            'openapi' => $this->version,
            'info' => $this->info->toArray() ?: (new InfoConfigurator())->build()->toArray(),
            'servers' => $this->normalizeCollection($this->servers),
            'paths' => $this->normalizeCollection($this->paths),
            'components' => $this->components->toArray(),
            'tags' => $this->normalizeCollection($this->tags),
            'externalDocs' => $this->externalDocs?->toArray(),
            'jsonSchemaDialect' => $this->jsonSchemaDialect,
            'webhooks' => $this->normalizeCollection($this->webhooks),
        ] + $this->getSpecificationExtensions());

        $exported += ['security' => $this->normalizeCollection($this->security)];

        return $exported;
    }
}
