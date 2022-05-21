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

use Lcobucci\JWT\Token;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\TokenBadge;
use Symfony\Component\Security\Http\Authenticator\Token\TokenAwareUserProviderInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 *
 * @final
 */
class TokenUserLoaderListener implements EventSubscriberInterface
{
    private readonly UserProviderInterface $userProvider;

    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['checkPassport', 2048]];
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(TokenBadge::class)) {
            return;
        }

        /** @var TokenBadge $badge */
        $badge = $passport->getBadge(TokenBadge::class);
        if (null !== $badge->getUserLoader()) {
            return;
        }

        if (!$this->userProvider instanceof TokenAwareUserProviderInterface) {
            return;
        }

        $badge->setUserLoader(function (string $identifier, ?Token $token): UserInterface {
            /* @phpstan-ignore-next-line */
            return $this->userProvider->loadUserByIdentifier($identifier, $token);
        });
    }
}
