<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class RegisterGlobalSecurityEventListenersPassTest extends TestCase
{
    private $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.debug', false);
        $this->container->register('request_stack', \stdClass::class);
        $this->container->register('event_dispatcher', EventDispatcher::class);

        $this->container->registerExtension(new SecurityExtension());

        $this->container->addCompilerPass(new RegisterListenersPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $this->container->getCompilerPassConfig()->setRemovingPasses([]);
        $this->container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $securityBundle = new SecurityBundle();
        $securityBundle->build($this->container);
    }

    /**
     * @dataProvider providePropagatedEvents
     */
    public function testEventIsPropagated(string $configuredEvent, string $registeredEvent)
    {
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true]],
        ]);

        $this->container->register('app.security_listener', \stdClass::class)
            ->addTag('kernel.event_listener', ['method' => 'onEvent', 'event' => $configuredEvent]);

        $this->container->compile();

        $this->assertListeners([
            [$registeredEvent, ['app.security_listener', 'onEvent'], 0],
        ]);
    }

    public static function providePropagatedEvents(): array
    {
        return [
            [CheckPassportEvent::class, CheckPassportEvent::class],
            [LoginFailureEvent::class, LoginFailureEvent::class],
            [LoginSuccessEvent::class, LoginSuccessEvent::class],
            [LogoutEvent::class, LogoutEvent::class],
            [AuthenticationTokenCreatedEvent::class, AuthenticationTokenCreatedEvent::class],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, AuthenticationEvents::AUTHENTICATION_SUCCESS],
            [SecurityEvents::INTERACTIVE_LOGIN, SecurityEvents::INTERACTIVE_LOGIN],

            // These events are ultimately registered by their event name instead of the FQN
            [AuthenticationSuccessEvent::class, AuthenticationEvents::AUTHENTICATION_SUCCESS],
            [InteractiveLoginEvent::class, SecurityEvents::INTERACTIVE_LOGIN],
        ];
    }

    public function testRegisterCustomListener()
    {
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true]],
        ]);

        $this->container->register('app.security_listener', \stdClass::class)
            ->addTag('kernel.event_listener', ['method' => 'onLogout', 'event' => LogoutEvent::class])
            ->addTag('kernel.event_listener', ['method' => 'onLoginSuccess', 'event' => LoginSuccessEvent::class, 'priority' => 20])
            ->addTag('kernel.event_listener', ['method' => 'onAuthenticationSuccess', 'event' => AuthenticationEvents::AUTHENTICATION_SUCCESS]);

        $this->container->compile();

        $this->assertListeners([
            [LogoutEvent::class, ['app.security_listener', 'onLogout'], 0],
            [LoginSuccessEvent::class, ['app.security_listener', 'onLoginSuccess'], 20],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, ['app.security_listener', 'onAuthenticationSuccess'], 0],
        ]);
    }

    public function testRegisterCustomSubscriber()
    {
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true]],
        ]);

        $this->container->register(TestSubscriber::class)
            ->addTag('kernel.event_subscriber');

        $this->container->compile();

        $this->assertListeners([
            [LogoutEvent::class, [TestSubscriber::class, 'onLogout'], -200],
            [CheckPassportEvent::class, [TestSubscriber::class, 'onCheckPassport'], 120],
            [LoginSuccessEvent::class, [TestSubscriber::class, 'onLoginSuccess'], 0],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, [TestSubscriber::class, 'onAuthenticationSuccess'], 0],
        ]);
    }

    public function testMultipleFirewalls()
    {
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true], 'api' => ['pattern' => '/api', 'http_basic' => true]],
        ]);

        $this->container->register('security.event_dispatcher.api', EventDispatcher::class)
            ->addTag('security.event_dispatcher')
            ->setPublic(true);

        $this->container->register('app.security_listener', \stdClass::class)
            ->addTag('kernel.event_listener', ['method' => 'onLogout', 'event' => LogoutEvent::class])
            ->addTag('kernel.event_listener', ['method' => 'onLoginSuccess', 'event' => LoginSuccessEvent::class, 'priority' => 20])
            ->addTag('kernel.event_listener', ['method' => 'onAuthenticationSuccess', 'event' => AuthenticationEvents::AUTHENTICATION_SUCCESS]);

        $this->container->compile();

        $this->assertListeners([
            [LogoutEvent::class, ['app.security_listener', 'onLogout'], 0],
            [LoginSuccessEvent::class, ['app.security_listener', 'onLoginSuccess'], 20],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, ['app.security_listener', 'onAuthenticationSuccess'], 0],
        ], 'security.event_dispatcher.main');
        $this->assertListeners([
            [LogoutEvent::class, ['app.security_listener', 'onLogout'], 0],
            [LoginSuccessEvent::class, ['app.security_listener', 'onLoginSuccess'], 20],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, ['app.security_listener', 'onAuthenticationSuccess'], 0],
        ], 'security.event_dispatcher.api');
    }

    public function testListenerAlreadySpecific()
    {
        $this->container->loadFromExtension('security', [
            'firewalls' => ['main' => ['pattern' => '/', 'http_basic' => true]],
        ]);

        $this->container->register('security.event_dispatcher.api', EventDispatcher::class)
            ->addTag('security.event_dispatcher')
            ->setPublic(true);

        $this->container->register('app.security_listener', \stdClass::class)
            ->addTag('kernel.event_listener', ['method' => 'onLogout', 'event' => LogoutEvent::class, 'dispatcher' => 'security.event_dispatcher.main'])
            ->addTag('kernel.event_listener', ['method' => 'onLoginSuccess', 'event' => LoginSuccessEvent::class, 'priority' => 20])
            ->addTag('kernel.event_listener', ['method' => 'onAuthenticationSuccess', 'event' => AuthenticationEvents::AUTHENTICATION_SUCCESS]);

        $this->container->compile();

        $this->assertListeners([
            [LogoutEvent::class, ['app.security_listener', 'onLogout'], 0],
            [LoginSuccessEvent::class, ['app.security_listener', 'onLoginSuccess'], 20],
            [AuthenticationEvents::AUTHENTICATION_SUCCESS, ['app.security_listener', 'onAuthenticationSuccess'], 0],
        ], 'security.event_dispatcher.main');
    }

    private function assertListeners(array $expectedListeners, string $dispatcherId = 'security.event_dispatcher.main')
    {
        $actualListeners = [];
        foreach ($this->container->findDefinition($dispatcherId)->getMethodCalls() as $methodCall) {
            [$method, $arguments] = $methodCall;
            if ('addListener' !== $method) {
                continue;
            }

            $arguments[1] = [(string) $arguments[1][0]->getValues()[0], $arguments[1][1]];
            $actualListeners[] = $arguments;
        }

        // PHP internally sorts all the arrays first, so returning proper 1 / -1 values is crucial
        $foundListeners = array_uintersect($expectedListeners, $actualListeners, fn (array $a, array $b) => $a <=> $b);

        $this->assertEquals($expectedListeners, $foundListeners);
    }
}

class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => ['onLogout', -200],
            CheckPassportEvent::class => ['onCheckPassport', 120],
            LoginSuccessEvent::class => 'onLoginSuccess',
            AuthenticationEvents::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }
}
