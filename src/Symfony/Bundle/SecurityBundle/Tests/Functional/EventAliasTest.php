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
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final class EventAliasTest extends AbstractWebTestCase
{
    public function testAliasedEvents()
    {
        $client = $this->createClient(['test_case' => 'AliasedEvents', 'root_config' => 'config.yml']);
        $container = $client->getContainer();
        $dispatcher = $container->get('event_dispatcher');

        $dispatcher->dispatch(new AuthenticationSuccessEvent(new UsernamePasswordToken(new InMemoryUser('John', 'password'), 'main')), AuthenticationEvents::AUTHENTICATION_SUCCESS);
        $dispatcher->dispatch(new InteractiveLoginEvent(new Request(), new UsernamePasswordToken(new InMemoryUser('John', 'password'), 'main')), SecurityEvents::INTERACTIVE_LOGIN);
        $dispatcher->dispatch(new SwitchUserEvent(new Request(), new InMemoryUser('John', 'password'), new UsernamePasswordToken(new InMemoryUser('Alice', 'password'), 'main')), SecurityEvents::SWITCH_USER);

        $this->assertEquals(
            [
                'onAuthenticationSuccess' => 1,
                'onInteractiveLogin' => 1,
                'onSwitchUser' => 1,
            ],
            $container->get('test_subscriber')->calledMethods
        );
    }
}
