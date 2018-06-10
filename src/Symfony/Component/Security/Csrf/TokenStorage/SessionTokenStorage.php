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
    const SESSION_NAMESPACE = '_csrf';

    private $session;
    private $namespace;

    /**
     * Initializes the storage with a Session object and a session namespace.
     *
     * @param SessionInterface $session   The user session from which the session ID is returned
     * @param string           $namespace The namespace under which the token is stored in the session
     */
    public function __construct(SessionInterface $session, string $namespace = self::SESSION_NAMESPACE)
    {
        $this->session = $session;
        $this->namespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($tokenId)
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        if (!$this->session->has($this->namespace.'/'.$tokenId)) {
            throw new TokenNotFoundException('The CSRF token with ID '.$tokenId.' does not exist.');
        }

        return (string) $this->session->get($this->namespace.'/'.$tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function setToken($tokenId, $token)
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        $this->session->set($this->namespace.'/'.$tokenId, (string) $token);
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken($tokenId)
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        return $this->session->has($this->namespace.'/'.$tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken($tokenId)
    {
        if (!$this->session->isStarted()) {
            $this->session->start();
        }

        return $this->session->remove($this->namespace.'/'.$tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach (array_keys($this->session->all()) as $key) {
            if (0 === strpos($key, $this->namespace.'/')) {
                $this->session->remove($key);
            }
        }
    }
}
