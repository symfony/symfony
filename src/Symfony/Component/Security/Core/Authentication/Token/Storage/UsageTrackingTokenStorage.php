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
use Symfony\Component\HttpFoundation\RequestStack;
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
    private $container;
    private $enableUsageTracking = false;

    public function __construct(TokenStorageInterface $storage, ContainerInterface $container)
    {
        $this->storage = $storage;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(): ?TokenInterface
    {
        if ($this->shouldTrackUsage()) {
            // increments the internal session usage index
            $this->getSession()->getMetadataBag();
        }

        return $this->storage->getToken();
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null): void
    {
        $this->storage->setToken($token);

        if ($token && $this->shouldTrackUsage()) {
            // increments the internal session usage index
            $this->getSession()->getMetadataBag();
        }
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
            'request_stack' => RequestStack::class,
        ];
    }

    private function getSession(): SessionInterface
    {
        // BC for symfony/security-bundle < 5.3
        if ($this->container->has('session')) {
            trigger_deprecation('symfony/security-core', '5.3', 'Injecting the "session" in "%s" is deprecated, inject the "request_stack" instead.', __CLASS__);

            return $this->container->get('session');
        }

        return $this->container->get('request_stack')->getSession();
    }

    private function shouldTrackUsage(): bool
    {
        if (!$this->enableUsageTracking) {
            return false;
        }

        // BC for symfony/security-bundle < 5.3
        if ($this->container->has('session')) {
            return true;
        }

        if (!$this->container->get('request_stack')->getMainRequest()) {
            return false;
        }

        return true;
    }
}
