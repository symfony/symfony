<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\AccessToken\Cas;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://apereo.github.io/cas/6.6.x/protocol/CAS-Protocol-V2-Specification.html
 *
 * @author Nicolas Attard <contact@nicolasattard.fr>
 */
final class Cas2Handler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $validationUrl,
        private readonly string $prefix = 'cas',
        private ?HttpClientInterface $client = null,
    ) {
        if (null === $client) {
            if (!class_exists(HttpClient::class)) {
                throw new \LogicException(\sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', __CLASS__));
            }

            $this->client = HttpClient::create();
        }
    }

    /**
     * @throws AuthenticationException
     */
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $response = $this->client->request('GET', $this->getValidationUrl($accessToken));

        $xml = new \SimpleXMLElement($response->getContent(), 0, false, $this->prefix, true);

        if (isset($xml->authenticationSuccess)) {
            return new UserBadge((string) $xml->authenticationSuccess->user);
        }

        if (isset($xml->authenticationFailure)) {
            throw new AuthenticationException('CAS Authentication Failure: '.trim((string) $xml->authenticationFailure));
        }

        throw new AuthenticationException('Invalid CAS response.');
    }

    private function getValidationUrl(string $accessToken): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Request should exist so it can be processed for error.');
        }

        $query = $request->query->all();

        if (!isset($query['ticket'])) {
            throw new AuthenticationException('No ticket found in request.');
        }
        unset($query['ticket']);
        $queryString = $query ? '?'.http_build_query($query) : '';

        return \sprintf('%s?ticket=%s&service=%s',
            $this->validationUrl,
            urlencode($accessToken),
            urlencode($request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo().$queryString)
        );
    }
}
