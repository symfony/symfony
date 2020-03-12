<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Provider;

use Symfony\Component\Security\OAuth2Client\Token\RefreshToken;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface ProviderInterface
{
    /**
     * Allow to parse the response body and find errors.
     */
    public function parseResponse(ResponseInterface $response);

    /**
     * This method allows to fetch the authorization informations,
     * this could be an authentication code as well as the client credentials.
     *
     * @param array  $options an array of extra options (scope, state, etc)
     * @param array  $headers an array of extra/overriding headers
     * @param string $method  the request http method
     *
     * @return mixed The authorization code (stored in a object if possible)
     */
    public function fetchAuthorizationInformations(array $options, array $headers = [], string $method = 'GET');

    /**
     * @param array  $options an array of extra options (scope, state, etc)
     * @param array  $headers an array of extra/overriding headers
     * @param string $method  the request http method
     *
     * @return mixed The access_token (stored in a object if possible)
     */
    public function fetchAccessToken(array $options, array $headers = [], string $method = 'GET');

    /**
     * Allow to refresh a token if the provider supports it.
     *
     * @param string      $refreshToken the refresh_token received in the access_token request
     * @param string|null $scope        the scope of the new access_token (must be supported by the provider)
     * @param array       $headers      an array of extra/overriding headers
     * @param string      $method       the request http method
     *
     * @return RefreshToken The newly token (with a valid refresh_token and scope).
     *
     * By default, the RefreshToken structure is similar to the AbstractToken one https://tools.ietf.org/html/rfc6749#section-5.1
     */
    public function refreshToken(string $refreshToken, string $scope = null, array $headers = [], string $method = 'GET'): RefreshToken;
}
