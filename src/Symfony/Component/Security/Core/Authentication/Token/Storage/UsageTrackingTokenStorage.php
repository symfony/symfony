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
    private TokenStorageInterface $storage;
    private ContainerInterface $container;
    private bool $enableUsageTracking = false;

    public function __construct(TokenStorageInterface $storage, ContainerInterface $container)
    {
        $this->storage = $storage;
        $this->container = $container;
    }

    public function getToken(): ?TokenInterface
    {
        if ($this->shouldTrackUsage()) {
            // increments the internal session usage index
            $this->getSession()->getMetadataBag();
        }

        return $this->storage->getToken();
    }

    public function setToken(TokenInterface $token = null): void
    {
        if (1 > \func_num_args()) {
            trigger_deprecation('symfony/security-core', '6.2', 'Calling "%s()" without any arguments is deprecated. Please explicitly pass null if you want to unset the token.', __METHOD__);
        }

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
        return $this->container->get('request_stack')->getSession();
    }

    private function shouldTrackUsage(): bool
    {
        return $this->enableUsageTracking && $this->container->get('request_stack')->getMainRequest();
    }
}
