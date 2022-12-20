<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SecurityDataCollectorTest extends TestCase
{
    public function testCollectWhenSecurityIsDisabled()
    {
        $collector = new SecurityDataCollector(null, null, null, null, null, null, true);
        $collector->collect(new Request(), new Response());

        self::assertSame('security', $collector->getName());
        self::assertFalse($collector->isEnabled());
        self::assertFalse($collector->isAuthenticated());
        self::assertFalse($collector->isImpersonated());
        self::assertNull($collector->getImpersonatorUser());
        self::assertNull($collector->getImpersonationExitPath());
        self::assertNull($collector->getTokenClass());
        self::assertFalse($collector->supportsRoleHierarchy());
        self::assertCount(0, $collector->getRoles());
        self::assertCount(0, $collector->getInheritedRoles());
        self::assertEmpty($collector->getUser());
        self::assertNull($collector->getFirewall());
    }

    public function testCollectWhenAuthenticationTokenIsNull()
    {
        $tokenStorage = new TokenStorage();
        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy(), null, null, null, null, true);
        $collector->collect(new Request(), new Response());

        self::assertTrue($collector->isEnabled());
        self::assertFalse($collector->isAuthenticated());
        self::assertFalse($collector->isImpersonated());
        self::assertNull($collector->getImpersonatorUser());
        self::assertNull($collector->getImpersonationExitPath());
        self::assertNull($collector->getTokenClass());
        self::assertTrue($collector->supportsRoleHierarchy());
        self::assertCount(0, $collector->getRoles());
        self::assertCount(0, $collector->getInheritedRoles());
        self::assertEmpty($collector->getUser());
        self::assertNull($collector->getFirewall());
        self::assertTrue($collector->isAuthenticatorManagerEnabled());
    }

    /** @dataProvider provideRoles */
    public function testCollectAuthenticationTokenAndRoles(array $roles, array $normalizedRoles, array $inheritedRoles)
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('hhamon', 'P4$$w0rD', $roles), 'provider', $roles));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy(), null, null, null, null, true);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertTrue($collector->isEnabled());
        self::assertTrue($collector->isAuthenticated());
        self::assertFalse($collector->isImpersonated());
        self::assertNull($collector->getImpersonatorUser());
        self::assertNull($collector->getImpersonationExitPath());
        self::assertSame('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $collector->getTokenClass()->getValue());
        self::assertTrue($collector->supportsRoleHierarchy());
        self::assertSame($normalizedRoles, $collector->getRoles()->getValue(true));
        self::assertSame($inheritedRoles, $collector->getInheritedRoles()->getValue(true));
        self::assertSame('hhamon', $collector->getUser());
        self::assertTrue($collector->isAuthenticatorManagerEnabled());
    }

    public function testCollectSwitchUserToken()
    {
        $adminToken = new UsernamePasswordToken(new InMemoryUser('yceruto', 'P4$$w0rD', ['ROLE_ADMIN']), 'provider', ['ROLE_ADMIN']);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new SwitchUserToken(new InMemoryUser('hhamon', 'P4$$w0rD', ['ROLE_USER', 'ROLE_PREVIOUS_ADMIN']), 'provider', ['ROLE_USER', 'ROLE_PREVIOUS_ADMIN'], $adminToken));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy(), null, null, null, null, true);
        $collector->collect(new Request(), new Response());
        $collector->lateCollect();

        self::assertTrue($collector->isEnabled());
        self::assertTrue($collector->isAuthenticated());
        self::assertTrue($collector->isImpersonated());
        self::assertSame('yceruto', $collector->getImpersonatorUser());
        self::assertSame(SwitchUserToken::class, $collector->getTokenClass()->getValue());
        self::assertTrue($collector->supportsRoleHierarchy());
        self::assertSame(['ROLE_USER', 'ROLE_PREVIOUS_ADMIN'], $collector->getRoles()->getValue(true));
        self::assertSame([], $collector->getInheritedRoles()->getValue(true));
        self::assertSame('hhamon', $collector->getUser());
    }

    public function testGetFirewall()
    {
        $firewallConfig = new FirewallConfig('dummy', 'security.request_matcher.dummy', 'security.user_checker.dummy');
        $request = new Request();

        $firewallMap = self::getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects(self::once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($firewallConfig);

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()), true);
        $collector->collect($request, new Response());
        $collector->lateCollect();
        $collected = $collector->getFirewall();

        self::assertSame($firewallConfig->getName(), $collected['name']);
        self::assertSame($firewallConfig->getRequestMatcher(), $collected['request_matcher']);
        self::assertSame($firewallConfig->isSecurityEnabled(), $collected['security_enabled']);
        self::assertSame($firewallConfig->isStateless(), $collected['stateless']);
        self::assertSame($firewallConfig->getProvider(), $collected['provider']);
        self::assertSame($firewallConfig->getContext(), $collected['context']);
        self::assertSame($firewallConfig->getEntryPoint(), $collected['entry_point']);
        self::assertSame($firewallConfig->getAccessDeniedHandler(), $collected['access_denied_handler']);
        self::assertSame($firewallConfig->getAccessDeniedUrl(), $collected['access_denied_url']);
        self::assertSame($firewallConfig->getUserChecker(), $collected['user_checker']);
        self::assertSame($firewallConfig->getAuthenticators(), $collected['authenticators']->getValue());
        self::assertTrue($collector->isAuthenticatorManagerEnabled());
    }

    public function testGetFirewallReturnsNull()
    {
        $request = new Request();
        $response = new Response();

        // Don't inject any firewall map
        $collector = new SecurityDataCollector(null, null, null, null, null, null, true);
        $collector->collect($request, $response);
        self::assertNull($collector->getFirewall());

        // Inject an instance that is not context aware
        $firewallMap = self::getMockBuilder(FirewallMapInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()), true);
        $collector->collect($request, $response);
        self::assertNull($collector->getFirewall());

        // Null config
        $firewallMap = self::getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()), true);
        $collector->collect($request, $response);
        self::assertNull($collector->getFirewall());
    }

    /**
     * @group time-sensitive
     */
    public function testGetListeners()
    {
        $request = new Request();
        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setResponse($response = new Response());
        $listener = function ($e) use ($event, &$listenerCalled) {
            $listenerCalled += $e === $event;
        };
        $firewallMap = self::getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects(self::any())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects(self::once())
            ->method('getListeners')
            ->with($request)
            ->willReturn([[$listener], null, null]);

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->onKernelRequest($event);

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, $firewall, true);
        $collector->collect($request, $response);

        self::assertNotEmpty($collected = $collector->getListeners()[0]);
        $collector->lateCollect();
        self::assertSame(1, $listenerCalled);
    }

    public function providerCollectDecisionLog(): \Generator
    {
        $voter1 = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();
        $voter2 = self::getMockBuilder(VoterInterface::class)->getMockForAbstractClass();

        $eventDispatcher = self::getMockBuilder(EventDispatcherInterface::class)->getMockForAbstractClass();
        $decoratedVoter1 = new TraceableVoter($voter1, $eventDispatcher);

        yield [
            MainConfiguration::STRATEGY_AFFIRMATIVE,
            [[
                'attributes' => ['view'],
                'object' => new \stdClass(),
                'result' => true,
                'voterDetails' => [
                    ['voter' => $voter1, 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['voter' => $voter2, 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                ],
            ]],
            [$decoratedVoter1, $decoratedVoter1],
            [\get_class($voter1), \get_class($voter2)],
            [[
                'attributes' => ['view'],
                'object' => new \stdClass(),
                'result' => true,
                'voter_details' => [
                    ['class' => \get_class($voter1), 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                    ['class' => \get_class($voter2), 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_ABSTAIN],
                ],
            ]],
        ];

        yield [
            MainConfiguration::STRATEGY_UNANIMOUS,
            [
                [
                    'attributes' => ['view', 'edit'],
                    'object' => new \stdClass(),
                    'result' => false,
                    'voterDetails' => [
                        ['voter' => $voter1, 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_DENIED],
                        ['voter' => $voter1, 'attributes' => ['edit'], 'vote' => VoterInterface::ACCESS_DENIED],
                        ['voter' => $voter2, 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_GRANTED],
                        ['voter' => $voter2, 'attributes' => ['edit'], 'vote' => VoterInterface::ACCESS_GRANTED],
                    ],
                ],
                [
                    'attributes' => ['update'],
                    'object' => new \stdClass(),
                    'result' => true,
                    'voterDetails' => [
                        ['voter' => $voter1, 'attributes' => ['update'], 'vote' => VoterInterface::ACCESS_GRANTED],
                        ['voter' => $voter2, 'attributes' => ['update'], 'vote' => VoterInterface::ACCESS_GRANTED],
                    ],
                ],
            ],
            [$decoratedVoter1, $decoratedVoter1],
            [\get_class($voter1), \get_class($voter2)],
            [
                [
                    'attributes' => ['view', 'edit'],
                    'object' => new \stdClass(),
                    'result' => false,
                    'voter_details' => [
                        ['class' => \get_class($voter1), 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_DENIED],
                        ['class' => \get_class($voter1), 'attributes' => ['edit'], 'vote' => VoterInterface::ACCESS_DENIED],
                        ['class' => \get_class($voter2), 'attributes' => ['view'], 'vote' => VoterInterface::ACCESS_GRANTED],
                        ['class' => \get_class($voter2), 'attributes' => ['edit'], 'vote' => VoterInterface::ACCESS_GRANTED],
                    ],
                ],
                [
                    'attributes' => ['update'],
                    'object' => new \stdClass(),
                    'result' => true,
                    'voter_details' => [
                        ['class' => \get_class($voter1), 'attributes' => ['update'], 'vote' => VoterInterface::ACCESS_GRANTED],
                        ['class' => \get_class($voter2), 'attributes' => ['update'], 'vote' => VoterInterface::ACCESS_GRANTED],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test the returned data when AccessDecisionManager is a TraceableAccessDecisionManager.
     *
     * @param string $strategy             strategy returned by the AccessDecisionManager
     * @param array  $voters               voters returned by AccessDecisionManager
     * @param array  $decisionLog          log of the votes and final decisions from AccessDecisionManager
     * @param array  $expectedVoterClasses expected voter classes returned by the collector
     * @param array  $expectedDecisionLog  expected decision log returned by the collector
     *
     * @dataProvider providerCollectDecisionLog
     */
    public function testCollectDecisionLog(string $strategy, array $decisionLog, array $voters, array $expectedVoterClasses, array $expectedDecisionLog)
    {
        $accessDecisionManager = self::getMockBuilder(TraceableAccessDecisionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(['getStrategy', 'getVoters', 'getDecisionLog'])
            ->getMock();

        $accessDecisionManager
            ->expects(self::any())
            ->method('getStrategy')
            ->willReturn($strategy);

        $accessDecisionManager
            ->expects(self::any())
            ->method('getVoters')
            ->willReturn($voters);

        $accessDecisionManager
            ->expects(self::any())
            ->method('getDecisionLog')
            ->willReturn($decisionLog);

        $dataCollector = new SecurityDataCollector(null, null, null, $accessDecisionManager, null, null, true);
        $dataCollector->collect(new Request(), new Response());

        self::assertEquals($dataCollector->getAccessDecisionLog(), $expectedDecisionLog, 'Wrong value returned by getAccessDecisionLog');

        self::assertSame(array_map(function ($classStub) { return (string) $classStub; }, $dataCollector->getVoters()), $expectedVoterClasses, 'Wrong value returned by getVoters');
        self::assertSame($dataCollector->getVoterStrategy(), $strategy, 'Wrong value returned by getVoterStrategy');
    }

    public function provideRoles()
    {
        return [
            // Basic roles
            [
                ['ROLE_USER'],
                ['ROLE_USER'],
                [],
            ],
            // Inherited roles
            [
                ['ROLE_ADMIN'],
                ['ROLE_ADMIN'],
                ['ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
            [
                ['ROLE_ADMIN', 'ROLE_OPERATOR'],
                ['ROLE_ADMIN', 'ROLE_OPERATOR'],
                ['ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
        ];
    }

    private function getRoleHierarchy()
    {
        return new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'],
            'ROLE_OPERATOR' => ['ROLE_USER'],
        ]);
    }
}
