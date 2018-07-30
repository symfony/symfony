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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * A token storage that optionally calls a closure when the token is accessed.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class UsageTrackingTokenStorage implements UsageTrackingTokenStorageInterface
{
    private $storage;
    private $usageTracker;

    public function __construct(TokenStorageInterface $storage = null)
    {
        $this->storage = $storage ?? new TokenStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function getToken(bool $trackUsage = true): ?TokenInterface
    {
        if ($trackUsage && $usageTracker = $this->usageTracker) {
            $usageTracker();
        }

        return $this->storage->getToken();
    }

    /**
     * {@inheritdoc}
     */
    public function setToken(TokenInterface $token = null, \Closure $usageTracker = null): void
    {
        $this->usageTracker = $usageTracker;
        $this->storage->setToken($token);
    }
}
