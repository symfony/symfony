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

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;

/**
 * Token storage that uses PHP's native session handling.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NativeSessionTokenStorage implements ClearableTokenStorageInterface
{
    /**
     * The namespace used to store values in the session.
     */
    public const SESSION_NAMESPACE = '_csrf';

    private bool $sessionStarted = false;
    private string $namespace;

    /**
     * Initializes the storage with a session namespace.
     *
     * @param string $namespace The namespace under which the token is stored in the session
     */
    public function __construct(string $namespace = self::SESSION_NAMESPACE)
    {
        $this->namespace = $namespace;
    }

    public function getToken(string $tokenId): string
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }

        if (!isset($_SESSION[$this->namespace][$tokenId])) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' does not exist.');
        }

        return (string) $_SESSION[$this->namespace][$tokenId];
    }

    public function setToken(string $tokenId, #[\SensitiveParameter] string $token): void
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }

        $_SESSION[$this->namespace][$tokenId] = $token;
    }

    public function hasToken(string $tokenId): bool
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }

        return isset($_SESSION[$this->namespace][$tokenId]);
    }

    public function removeToken(string $tokenId): ?string
    {
        if (!$this->sessionStarted) {
            $this->startSession();
        }

        if (!isset($_SESSION[$this->namespace][$tokenId])) {
            return null;
        }

        $token = (string) $_SESSION[$this->namespace][$tokenId];

        unset($_SESSION[$this->namespace][$tokenId]);

        if (!$_SESSION[$this->namespace]) {
            unset($_SESSION[$this->namespace]);
        }

        return $token;
    }

    public function clear(): void
    {
        unset($_SESSION[$this->namespace]);
    }

    private function startSession(): void
    {
        if (\PHP_SESSION_NONE === session_status()) {
            session_start();
        }

        $this->sessionStarted = true;
    }
}
