<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Accesses tokens in a set of cookies. A changeset records edits made to
 * tokens. The changeset can be retrieved as a list of cookies to be used in a
 * response's headers to "persist" the changes.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class CookieTokenStorage implements TokenStorageInterface
{
    /**
     * @var array
     */
    private $transientTokens = array();

    /**
     * @var ParameterBag
     */
    private $cookies;

    /**
     * @var bool
     */
    private $secure;

    /**
     * @param ParameterBag $cookies
     * @param bool         $secure
     */
    public function __construct(ParameterBag $cookies, $secure)
    {
        $this->cookies = $cookies;
        $this->secure = (bool) $secure;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($tokenId)
    {
        $token = $this->resolveToken($tokenId);

        if ('' === $token) {
            throw new TokenNotFoundException();
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken($tokenId)
    {
        return '' !== $this->resolveToken($tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken($tokenId, $token)
    {
        $token = (string) $token;

        if ('' === $token) {
            throw new InvalidArgumentException('Empty tokens are not allowed');
        }

        $this->updateToken($tokenId, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        $token = $this->resolveToken($tokenId);

        $this->updateToken($tokenId, '');

        return '' === $token ? null : $token;
    }

    /**
     * @return array
     */
    public function createCookies()
    {
        $cookies = array();

        foreach ($this->transientTokens as $tokenId => $token) {
            // FIXME empty tokens are handled by the http foundations cookie class
            // and are recognized as a "delete" cookie
            // the problem is the that the value of deleted cookies get set to
            // the string "deleted" and not the empty string

            $name = $this->generateCookieName($tokenId);
            $cookies[] = new Cookie($name, $token, 0, null, null, $this->secure, true);
        }

        return $cookies;
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function resolveToken($tokenId)
    {
        if (isset($this->transientTokens[$tokenId])) {
            return $this->transientTokens[$tokenId];
        }

        $name = $this->generateCookieName($tokenId);

        return $this->cookies->get($name, '');
    }

    /**
     * @param string $tokenId
     * @param string $token
     */
    protected function updateToken($tokenId, $token)
    {
        $name = $this->generateCookieName($tokenId);

        if ($token === $this->cookies->get($name, '')) {
            unset($this->transientTokens[$tokenId]);
        } else {
            $this->transientTokens[$tokenId] = $token;
        }
    }

    /**
     * @param string $tokenId
     *
     * @return string
     */
    protected function generateCookieName($tokenId)
    {
        return sprintf('_csrf/%s/%s', $this->secure ? 'insecure' : 'secure', $tokenId);
    }
}
