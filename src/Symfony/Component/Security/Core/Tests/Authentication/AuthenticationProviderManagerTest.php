<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSensitiveEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Contracts\EventDispatcher\Event;

class AuthenticationProviderManagerTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAuthenticateWithoutProviders()
    {
        new AuthenticationProviderManager([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAuthenticateWithProvidersWithIncorrectInterface()
    {
        (new AuthenticationProviderManager([
            new \stdClass(),
        ]))->authenticate($this->getMockBuilder(TokenInterface::class)->getMock());
    }

    public function testAuthenticateWhenNoProviderSupportsToken()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(false),
        ]);

        try {
            $manager->authenticate($token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
            $this->fail();
        } catch (ProviderNotFoundException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAccountStatusException()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Core\Exception\AccountStatusException'),
        ]);

        try {
            $manager->authenticate($token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
            $this->fail();
        } catch (AccountStatusException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenProviderReturnsAuthenticationException()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Core\Exception\AuthenticationException'),
        ]);

        try {
            $manager->authenticate($token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
            $this->fail();
        } catch (AuthenticationException $e) {
            $this->assertSame($token, $e->getToken());
        }
    }

    public function testAuthenticateWhenOneReturnsAuthenticationExceptionButNotAll()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, null, 'Symfony\Component\Security\Core\Exception\AuthenticationException'),
            $this->getAuthenticationProvider(true, $expected = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()),
        ]);

        $token = $manager->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $this->assertSame($expected, $token);
    }

    public function testAuthenticateReturnsTokenOfTheFirstMatchingProvider()
    {
        $second = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface')->getMock();
        $second
            ->expects($this->never())
            ->method('supports')
        ;
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $expected = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock()),
            $second,
        ]);

        $token = $manager->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $this->assertSame($expected, $token);
    }

    public function testEraseCredentialFlag()
    {
        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ]);

        $token = $manager->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $this->assertEquals('', $token->getCredentials());

        $manager = new AuthenticationProviderManager([
            $this->getAuthenticationProvider(true, $token = new UsernamePasswordToken('foo', 'bar', 'key')),
        ], false);

        $token = $manager->authenticate($this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock());
        $this->assertEquals('bar', $token->getCredentials());
    }

    public function testAuthenticateDispatchesAuthenticationFailureEvent()
    {
        $token = new UsernamePasswordToken('foo', 'bar', 'key');
        $provider = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface')->getMock();
        $provider->expects($this->once())->method('supports')->willReturn(true);
        $provider->expects($this->once())->method('authenticate')->willThrowException($exception = new AuthenticationException());

        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo(new AuthenticationFailureEvent($token, $exception)), AuthenticationEvents::AUTHENTICATION_FAILURE);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        try {
            $manager->authenticate($token);
            $this->fail('->authenticate() should rethrow exceptions');
        } catch (AuthenticationException $e) {
            $this->assertSame($token, $exception->getToken());
        }
    }

    public function testAuthenticateDispatchesAuthenticationSuccessEvents()
    {
        $finalToken = new UsernamePasswordToken('foo', 'bar', 'baz', ['role-01', 'role-02']);
        $priorToken = new UsernamePasswordToken('foo', 'bar', 'baz');

        $provider = $this->getAuthenticationProvider(true, $finalToken);
        $providerCN = \get_class($provider);

        $dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)->getMock();
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive([
                $this->equalTo(new AuthenticationSensitiveEvent($priorToken, $finalToken, $providerCN)), AuthenticationEvents::AUTHENTICATION_SUCCESS_SENSITIVE,
            ], [
                $this->equalTo(new AuthenticationSuccessEvent($finalToken)), AuthenticationEvents::AUTHENTICATION_SUCCESS,
            ]);

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        $this->assertSame($finalToken, $manager->authenticate($priorToken));
    }

    public function testAuthenticateDispatchesAuthenticationSuccessEventsWithCredentialsAvailableAndRemovedForSuccessiveDispatches()
    {
        $finalToken = new UsernamePasswordToken('foo', 'bar', 'baz', ['role-01', 'role-02']);
        $priorToken = new UsernamePasswordToken('foo', 'bar', 'baz');

        $provider = $this->getAuthenticationProvider(true, $finalToken);
        $providerCN = \get_class($provider);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AuthenticationEvents::AUTHENTICATION_SUCCESS_SENSITIVE, function ($event) use ($providerCN) {
            if ('Symfony\Component\EventDispatcher\WrappedEvent' === \get_class($event)) {
                $event = $event->getWrappedEvent();
            }

            /* @var AuthenticationSensitiveEvent $event */
            $this->assertSame($providerCN, $event->getAuthenticationProviderClassName());
            $this->assertSame('bar', $event->getAuthenticationTokenPassword());
            $this->assertEquals('bar', $event->getPreAuthenticationToken()->getCredentials());
            $this->assertEquals('bar', $event->getAuthenticationToken()->getCredentials());
        });
        $dispatcher->addListener(AuthenticationEvents::AUTHENTICATION_SUCCESS, function ($event) {
            if ('Symfony\Component\EventDispatcher\WrappedEvent' === \get_class($event)) {
                $event = $event->getWrappedEvent();
            }

            /* @var AuthenticationSuccessEvent $event */
            $this->assertEquals('', $event->getAuthenticationToken()->getCredentials());
        });

        $manager = new AuthenticationProviderManager([$provider]);
        $manager->setEventDispatcher($dispatcher);

        $this->assertSame($finalToken, $manager->authenticate($priorToken));
    }

    protected function getAuthenticationProvider($supports, $token = null, $exception = null)
    {
        $provider = $this->getMockBuilder(AuthenticationProviderInterface::class)->getMock();
        $provider->expects($this->once())
                 ->method('supports')
                 ->will($this->returnValue($supports))
        ;

        if (null !== $token) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->will($this->returnValue($token))
            ;
        } elseif (null !== $exception) {
            $provider->expects($this->once())
                     ->method('authenticate')
                     ->willThrowException($this->getMockBuilder($exception)->setMethods(null)->getMock())
            ;
        }

        return $provider;
    }
}
