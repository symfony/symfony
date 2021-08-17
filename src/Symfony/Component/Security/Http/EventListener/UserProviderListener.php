<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Configures the user provider as user loader, if no user load
 * has been explicitly set.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class UserProviderListener
{
    private $userProvider;

    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(UserBadge::class)) {
            return;
        }

        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);
        if (null !== $badge->getUserLoader()) {
            return;
        }

        // @deprecated since Symfony 5.3, change to $this->userProvider->loadUserByIdentifier() in 6.0
        if (method_exists($this->userProvider, 'loadUserByIdentifier')) {
            $badge->setUserLoader([$this->userProvider, 'loadUserByIdentifier']);
        } else {
            trigger_deprecation('symfony/security-http', '5.3', 'Not implementing method "loadUserByIdentifier()" in user provider "%s" is deprecated. This method will replace "loadUserByUsername()" in Symfony 6.0.', get_debug_type($this->userProvider));

            $badge->setUserLoader([$this->userProvider, 'loadUserByUsername']);
        }
    }
}
