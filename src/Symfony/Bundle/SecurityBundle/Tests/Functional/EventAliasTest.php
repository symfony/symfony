<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final class EventAliasTest extends AbstractWebTestCase
{
    public function testAliasedEvents(): void
    {
        $client = $this->createClient(['test_case' => 'AliasedEvents', 'root_config' => 'config.yml']);
        $container = $client->getContainer();
        $dispatcher = $container->get('event_dispatcher');

        $dispatcher->dispatch(new AuthenticationSuccessEvent($this->createMock(TokenInterface::class)), AuthenticationEvents::AUTHENTICATION_SUCCESS);
        $dispatcher->dispatch(new AuthenticationFailureEvent($this->createMock(TokenInterface::class), new AuthenticationException()), AuthenticationEvents::AUTHENTICATION_FAILURE);
        $dispatcher->dispatch(new InteractiveLoginEvent($this->createMock(Request::class), $this->createMock(TokenInterface::class)), SecurityEvents::INTERACTIVE_LOGIN);
        $dispatcher->dispatch(new SwitchUserEvent($this->createMock(Request::class), $this->createMock(UserInterface::class), $this->createMock(TokenInterface::class)), SecurityEvents::SWITCH_USER);

        $this->assertEquals(
            [
                'onAuthenticationSuccess' => 1,
                'onAuthenticationFailure' => 1,
                'onInteractiveLogin' => 1,
                'onSwitchUser' => 1,
            ],
            $container->get('test_subscriber')->calledMethods
        );
    }
}
