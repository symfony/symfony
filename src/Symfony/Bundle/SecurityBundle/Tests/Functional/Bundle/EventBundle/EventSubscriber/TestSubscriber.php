<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\EventBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;

final class TestSubscriber implements EventSubscriberInterface
{
    public $calledMethods = [];

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
            AuthenticationFailureEvent::class => 'onAuthenticationFailure',
            InteractiveLoginEvent::class => 'onInteractiveLogin',
            SwitchUserEvent::class => 'onSwitchUser',
        ];
    }

    public function __call(string $name, array $arguments)
    {
        $this->calledMethods[$name] = ($this->calledMethods[$name] ?? 0) + 1;
    }
}
