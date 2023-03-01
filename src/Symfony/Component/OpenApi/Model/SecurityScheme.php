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
class SecurityScheme implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        private readonly string $type,
        private readonly ?string $name = null,
        private readonly ?SecuritySchemeIn $in = null,
        private readonly ?string $scheme = null,
        private readonly ?string $description = null,
        private readonly ?string $bearerFormat = null,
        private readonly ?string $openIdConnectUrl = null,
        private readonly ?OauthFlows $flows = null,
        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getIn(): ?SecuritySchemeIn
    {
        return $this->in;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getBearerFormat(): ?string
    {
        return $this->bearerFormat;
    }

    public function getOpenIdConnectUrl(): ?string
    {
        return $this->openIdConnectUrl;
    }

    public function getFlows(): ?OauthFlows
    {
        return $this->flows;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->getType(),
            'description' => $this->getDescription(),
            // invalid in 3.1
//            'name' => $this->getName(),
//            'in' => $this->getIn()?->value,
            'scheme' => $this->getScheme(),
            'bearerFormat' => $this->getBearerFormat(),
            'flows' => $this->getFlows()?->toArray(),
            'openIdConnectUrl' => $this->getOpenIdConnectUrl(),
        ] + $this->getSpecificationExtensions());
    }
}
