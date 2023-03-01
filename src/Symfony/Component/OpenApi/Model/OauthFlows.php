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
class OauthFlows implements OpenApiModel
{
    use OpenApiTrait;

    public function __construct(
        #[Assert\Valid]
        private readonly ?OauthFlow $implicit = null,

        #[Assert\Valid]
        private readonly ?OauthFlow $password = null,

        #[Assert\Valid]
        private readonly ?OauthFlow $clientCredentials = null,

        #[Assert\Valid]
        private readonly ?OauthFlow $authorizationCode = null,

        private readonly array $specificationExtensions = [],
    ) {
    }

    public function getImplicit(): ?OauthFlow
    {
        return $this->implicit;
    }

    public function getPassword(): ?OauthFlow
    {
        return $this->password;
    }

    public function getClientCredentials(): ?OauthFlow
    {
        return $this->clientCredentials;
    }

    public function getAuthorizationCode(): ?OauthFlow
    {
        return $this->authorizationCode;
    }

    public function getSpecificationExtensions(): array
    {
        return $this->specificationExtensions;
    }

    public function toArray(): array
    {
        return array_filter([
            'implicit' => $this->getImplicit()?->toArray(),
            'password' => $this->getPassword()?->toArray(),
            'clientCredentials' => $this->getClientCredentials()?->toArray(),
            'authorizationCode' => $this->getAuthorizationCode()?->toArray(),
        ] + $this->getSpecificationExtensions());
    }
}
