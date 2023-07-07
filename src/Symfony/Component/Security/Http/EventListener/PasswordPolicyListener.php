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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\PasswordPolicyException;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Policy\PasswordPolicyInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

final class PasswordPolicyListener implements EventSubscriberInterface
{
    /**
     * @param PasswordPolicyInterface[] $policies
     */
    public function __construct(private readonly array $policies)
    {
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $badge = $passport->getBadge(PasswordCredentials::class);
        if ($badge->isResolved()) {
            return;
        }

        $plaintextPassword = $badge->getPassword();
        foreach ($this->policies as $policy) {
            if (!$policy->verify($plaintextPassword)) {
                throw new PasswordPolicyException();
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['checkPassport', 512],
        ];
    }
}
