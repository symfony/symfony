<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Oidc;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches and caches the OpenID Connect discovery configuration.
 *
 * @see https://openid.net/specs/openid-connect-discovery-1_0.html
 *
 * @author Mathieu Music <music.music@gmail.com>
 */
final class OidcDiscovery
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly string $openIdConfigurationUrl,
        private readonly int $cacheTtl = 3600,
    ) {
    }

    public function getConfiguration(): OidcConfiguration
    {
        $data = $this->cache->get('oidc_discovery.'.hash('xxh128', $this->openIdConfigurationUrl), function (ItemInterface $item): array {
            $item->expiresAfter($this->cacheTtl);

            return $this->httpClient->request('GET', $this->openIdConfigurationUrl)->toArray();
        });

        return OidcConfiguration::fromArray($data);
    }
}
