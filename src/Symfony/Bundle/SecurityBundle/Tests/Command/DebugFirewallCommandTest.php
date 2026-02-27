<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Command\DebugFirewallCommand;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Tests\Fixtures\DummyAuthenticator;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;

class DebugFirewallCommandTest extends TestCase
{
    public function testFirewallListOutputMatchesFixture()
    {
        $firewallNames = ['main', 'api'];

        $command = new DebugFirewallCommand($firewallNames, new Container(), new Container(), []);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Firewalls', $tester->getDisplay());
        $this->assertStringContainsString('The following firewalls are defined:', $tester->getDisplay());
        $this->assertStringContainsString('* main', $tester->getDisplay());
        $this->assertStringContainsString('* api', $tester->getDisplay());
        $this->assertStringContainsString('To view details of a specific firewall', $tester->getDisplay());
    }

    public function testFirewallNotFoundDisplaysError()
    {
        $firewallNames = ['main', 'api'];

        $authenticators = [];

        $command = new DebugFirewallCommand(
            $firewallNames,
            new Container(),
            new Container(),
            $authenticators
        );

        $tester = new CommandTester($command);

        $this->assertSame(1, $tester->execute(['name' => 'admin']));
        $this->assertStringContainsString('Firewall admin was not found.', $tester->getDisplay());
        $this->assertStringContainsString('Available firewalls are: main, api', $tester->getDisplay());
    }

    public function testFirewallMainOutputMatchesFixture()
    {
        $firewallNames = ['main'];

        $config = new FirewallConfig(
            name: 'main',
            userChecker: 'user_checker_service',
            requestMatcher: null,
            securityEnabled: true,
            stateless: false,
            provider: 'user_provider_service',
            context: 'main',
            entryPoint: 'entry_point_service',
            accessDeniedHandler: 'access_denied_handler_service',
            accessDeniedUrl: '/access-denied',
            authenticators: [],
            switchUser: null
        );

        $context = new FirewallContext([], config: $config);

        $contexts = new Container();
        $contexts->set('security.firewall.map.context.main', $context);

        $eventDispatchers = new Container();
        $authenticator = new DummyAuthenticator();
        $authenticators = ['main' => [$authenticator]];

        $command = new DebugFirewallCommand($firewallNames, $contexts, $eventDispatchers, $authenticators);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['name' => 'main', '--events' => true]));
        $this->assertEquals($this->getFixtureOutput('firewall_main_output.txt'), trim(str_replace(\PHP_EOL, "\n", $tester->getDisplay())));
    }

    public function testFirewallWithEventsOutputMatchesFixture()
    {
        $firewallNames = ['main'];

        $config = new FirewallConfig(
            name: 'main',
            userChecker: 'user_checker_service',
            context: 'main',
            stateless: false,
            provider: 'user_provider_service',
            entryPoint: 'entry_point_service',
            accessDeniedHandler: 'access_denied_handler_service',
            accessDeniedUrl: '/access-denied',
        );

        $context = new FirewallContext([], config: $config);

        $contexts = new Container();
        $contexts->set('security.firewall.map.context.main', $context);

        $dispatcher = new EventDispatcher();
        $listener = static fn () => null;
        $listenerTwo = static fn (int $number) => $number * 2;
        $dispatcher->addListener('security.event', $listener, 42);
        $dispatcher->addListener('security.event', $listenerTwo, 42);

        $eventDispatchers = new Container();
        $eventDispatchers->set('security.event_dispatcher.main', $dispatcher);

        $authenticator = new DummyAuthenticator();
        $authenticatorTwo = new DummyAuthenticator();
        $authenticatorThree = new DummyAuthenticator();
        $authenticators = ['main' => [$authenticator, $authenticatorTwo], 'api' => [$authenticatorThree]];

        $command = new DebugFirewallCommand($firewallNames, $contexts, $eventDispatchers, $authenticators);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['name' => 'main', '--events' => true]));
        $this->assertEquals($this->getFixtureOutput('firewall_main_with_events_output.txt'), trim(str_replace(\PHP_EOL, "\n", $tester->getDisplay())));
    }

    public function testFirewallWithSwitchUserDisplaysSection()
    {
        $firewallNames = ['main'];

        $switchUserConfig = [
            'parameter' => '_switch_user_test',
            'provider' => 'custom_provider_test',
            'role' => 'ROLE_ALLOWED_TO_SWITCH',
        ];

        $config = new FirewallConfig(
            name: 'main',
            userChecker: 'user_checker_service_test',
            context: 'main',
            stateless: false,
            provider: 'user_provider_service_test',
            entryPoint: 'entry_point_service_test',
            accessDeniedHandler: 'access_denied_handler_service_test',
            accessDeniedUrl: '/access-denied-test',
            switchUser: $switchUserConfig,
        );

        $context = new FirewallContext([], config: $config);

        $contexts = new Container();
        $contexts->set('security.firewall.map.context.main', $context);

        $eventDispatchers = new Container();
        $authenticator = new DummyAuthenticator();
        $authenticatorTwo = $this->createStub(AuthenticatorInterface::class);
        $authenticators = ['main' => [$authenticator], 'api' => [$authenticatorTwo]];

        $command = new DebugFirewallCommand(
            $firewallNames,
            $contexts,
            $eventDispatchers,
            $authenticators
        );
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['name' => 'main']));
        $this->assertEquals($this->getFixtureOutput('firewall_main_with_switch_user.txt'), trim(str_replace(\PHP_EOL, "\n", $tester->getDisplay())));
    }

    private function getFixtureOutput(string $file): string
    {
        return trim(file_get_contents(__DIR__.'/../Fixtures/Descriptor/'.$file));
    }
}
