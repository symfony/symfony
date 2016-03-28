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

/**
 * Forwards calls to another TokenStorageInterface
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
abstract class AbstractTokenStorageProxy implements TokenStorageInterface
{
    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface::getToken()
     */
    public function getToken($tokenId)
    {
        return $this->getProxiedTokenStorage()->getToken($tokenId);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface::setToken()
     */
    public function setToken($tokenId, $token)
    {
        // TODO interface declares return void, use return stmt or not?
        $this->getProxiedTokenStorage()->setToken($tokenId, $token);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface::removeToken()
     */
    public function removeToken($tokenId)
    {
        return $this->getProxiedTokenStorage()->removeToken($tokenId);
    }

    /**
     * {@inheritDoc}
     * @see \Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface::hasToken()
     */
    public function hasToken($tokenId)
    {
        return $this->getProxiedTokenStorage()->hasToken($tokenId);
    }

    /**
     * @return TokenStorageInterface
     */
    abstract protected function getProxiedTokenStorage();
}
