<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\LazyTokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * Enforces access control rules while allowing unauthenticated access when no attributes are found.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class LazyAccessListener extends AccessListener
{
    private $tokenStorage;
    private $map;

    public function __construct(LazyTokenStorage $tokenStorage, AccessDecisionManagerInterface $accessDecisionManager, AccessMapInterface $map, AuthenticationManagerInterface $authManager)
    {
        parent::__construct($tokenStorage, $accessDecisionManager, $map, $authManager);
        $this->tokenStorage = $tokenStorage;
        $this->map = $map;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(GetResponseEvent $event)
    {
        list($attributes) = $this->map->getPatterns($event->getRequest());

        if ($attributes) {
            return parent::handle($event);
        }
    }

    public function getTokenStorage(): LazyTokenStorage
    {
        return $this->tokenStorage;
    }
}
