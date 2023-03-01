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

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class OauthFlow implements OpenApiModel
{
    use OpenApiTrait;

    /**
     * @param array<string, string> $scopes
     */
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $authorizationUrl,

        #[Assert\NotBlank]
        private readonly string $tokenUrl,

        #[Assert\NotBlank]
        #[Assert\All([new Assert\Type('string')])]
        private readonly array $scopes = [],

        private readonly ?string $refreshUrl = null,

        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function getTokenUrl(): string
    {
        return $this->tokenUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function getRefreshUrl(): ?string
    {
        return $this->refreshUrl;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'authorizationUrl' => $this->getAuthorizationUrl(),
            'tokenUrl' => $this->getTokenUrl(),
            'refreshUrl' => $this->getRefreshUrl(),
            'scopes' => $this->normalizeCollection($this->getScopes()),
        ] + $this->getSpecificationExtensions());
    }
}
