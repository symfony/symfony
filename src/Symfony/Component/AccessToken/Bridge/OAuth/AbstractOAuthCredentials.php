<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Bridge\OAuth;

use Symfony\Component\AccessToken\Credentials\AbstractCredentials;

/**
 * OAuth2 credentials.
 *
 * @see https://www.oauth.com/oauth2-servers/access-tokens/
 *
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
abstract class AbstractOAuthCredentials extends AbstractCredentials
{
    /**
     * @param null|string $tenant   Tenant name or identifier
     * @param null|string $endpoint Authorization endpoint URL, for generic usage you must provide one
     */
    public function __construct(
        #[\SensitiveParameter] protected readonly ?string $tenant = null,
        protected readonly ?string $endpoint = null,
    ) {}

    /**
     * Get grant type.
     */
    public abstract function getGrantType(): string;

    /**
     * Get tenant name or identifier.
     */
    public function getTenant(): ?string
    {
        return $this->tenant;
    }

    /**
     * Get endpoint URL.
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }
}
