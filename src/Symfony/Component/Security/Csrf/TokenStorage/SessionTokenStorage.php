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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
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

    private $requestStack;
    private $namespace;
    /**
     * To be removed in Symfony 6.0.
     */
    private $session;

    /**
     * Initializes the storage with a RequestStack object and a session namespace.
     *
     * @param RequestStack $requestStack
     * @param string       $namespace    The namespace under which the token is stored in the requestStack
     */
    public function __construct(/* RequestStack*/ $requestStack, string $namespace = self::SESSION_NAMESPACE)
    {
        if ($requestStack instanceof SessionInterface) {
            trigger_deprecation('symfony/security-csrf', '5.3', 'Passing a "%s" to "%s" is deprecated, use a "%s" instead.', SessionInterface::class, __CLASS__, RequestStack::class);
            $request = new Request();
            $request->setSession($requestStack);

            $requestStack = new RequestStack();
            $requestStack->push($request);
        }
        $this->requestStack = $requestStack;
        $this->namespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(string $tokenId)
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

    /**
     * {@inheritdoc}
     */
    public function setToken(string $tokenId, string $token)
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set($this->namespace.'/'.$tokenId, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function hasToken(string $tokenId)
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->has($this->namespace.'/'.$tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function removeToken(string $tokenId)
    {
        $session = $this->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        return $session->remove($this->namespace.'/'.$tokenId);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $session = $this->getSession();
        foreach (array_keys($session->all()) as $key) {
            if (0 === strpos($key, $this->namespace.'/')) {
                $session->remove($key);
            }
        }
    }

    private function getSession(): SessionInterface
    {
        try {
            return $this->session ?? $this->requestStack->getSession();
        } catch (SessionNotFoundException $e) {
            trigger_deprecation('symfony/security-csrf', '5.3', 'Using the "%s" without a session has no effect and is deprecated. It will throw a "%s" in Symfony 6.0', __CLASS__, SessionNotFoundException::class);

            return $this->session ?? $this->session = new Session(new MockArraySessionStorage());
        }
    }
}
