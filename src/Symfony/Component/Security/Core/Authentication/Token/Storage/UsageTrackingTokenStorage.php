<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Token\Storage;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * A token storage that increments the session usage index when the token is accessed.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class UsageTrackingTokenStorage implements TokenStorageInterface, ServiceSubscriberInterface
{
    private $storage;
    private $sessionLocator;
    private $enableUsageTracking = false;

    public function __construct(TokenStorageInterface $storage, ContainerInterface $sessionLocator)
    {
        $this->storage = $storage;
        $this->sessionLocator = $sessionLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(): ?TokenInterface
    {
        if ($this->enableUsageTracking) {
            // increments the internal session usage index
            $this->sessionLocator->get('session')->getMetadataBag();
        }

        return $this->storage->getToken();
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null): void
    {
        $this->storage->setToken($token);
    }

    public function enableUsageTracking(): void
    {
        $this->enableUsageTracking = true;
    }

    public function disableUsageTracking(): void
    {
        $this->enableUsageTracking = false;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'session' => SessionInterface::class,
        ];
    }
}
