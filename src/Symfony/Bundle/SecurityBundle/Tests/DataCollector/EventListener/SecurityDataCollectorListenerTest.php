<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DataCollector\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\EventListener\SecurityDataCollectorListener;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;

class SecurityDataCollectorListenerTest extends TestCase
{
    public function testDeauthenticationEventIsForwardedToTheCollector()
    {
        $collector = new SecurityDataCollector(new TokenStorage());
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new SecurityDataCollectorListener($collector));

        $token = new UsernamePasswordToken(new InMemoryUser('jane', 'password'), 'main', ['ROLE_USER']);
        $dispatcher->dispatch(new TokenDeauthenticatedEvent($token, new Request(), 'the user has changed', [InMemoryUserProvider::class]));

        $collector->collect(new Request(), new Response());

        $this->assertSame([
            'reason' => 'the user has changed',
            'providers' => [InMemoryUserProvider::class],
            'user' => 'jane',
        ], $collector->getDeauthentication());
    }
}
