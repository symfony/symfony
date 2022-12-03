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

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Token storage that uses a Symfony Session object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SessionTokenStorage implements ClearableTokenStorageInterface
{
    /**
     * The namespace used to store values in the session.
     */
    public const SESSION_NAMESPACE = '_csrf';

    private RequestStack $requestStack;
    private string $namespace;

    /**
     * Initializes the storage with a RequestStack object and a session namespace.
     *
     * @param string $namespace The namespace under which the token is stored in the requestStack
     */
    public function __construct(RequestStack $requestStack, string $namespace = self::SESSION_NAMESPACE)
    {
        $this->requestStack = $requestStack;
        $this->namespace = $namespace;
    }

    public function getToken(string $tokenId): string
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        if (!$session->has($this->namespace.'/'.$tokenId)) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' does not exist.');
        }

        return (string) $session->get($this->namespace.'/'.$tokenId);
    }

    public function setToken(string $tokenId, #[\SensitiveParameter] string $token)
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set($this->namespace.'/'.$tokenId, $token);
    }

    public function hasToken(string $tokenId): bool
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->has($this->namespace.'/'.$tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->remove($this->namespace.'/'.$tokenId);
    }

    public function clear()
    {
        $session = $this->getSession();
        foreach (array_keys($session->all()) as $key) {
            if (str_starts_with($key, $this->namespace.'/')) {
                $session->remove($key);
            }
        }
    }

    /**
     * @throws SessionNotFoundException
     */
    private function getSession(): SessionInterface
    {
        return $this->requestStack->getSession();
    }
}
