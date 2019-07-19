<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder;
use Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder;

abstract class CompleteConfigurationTest extends TestCase
{
    abstract protected function getLoader(ContainerBuilder $container);

    abstract protected function getFileExtension();

    public function testRolesHierarchy()
    {
        $container = $this->getContainer('container1');
        $this->assertEquals([
            'ROLE_ADMIN' => ['ROLE_USER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            'ROLE_REMOTE' => ['ROLE_USER', 'ROLE_ADMIN'],
        ], $container->getParameter('security.role_hierarchy.roles'));
    }

    public function testUserProviders()
    {
        $container = $this->getContainer('container1');

        $providers = array_values(array_filter($container->getServiceIds(), function ($key) { return 0 === strpos($key, 'security.user.provider.concrete'); }));

        $expectedProviders = [
            'security.user.provider.concrete.default',
            'security.user.provider.concrete.digest',
            'security.user.provider.concrete.basic',
            'security.user.provider.concrete.service',
            'security.user.provider.concrete.chain',
        ];

        $this->assertEquals([], array_diff($expectedProviders, $providers));
        $this->assertEquals([], array_diff($providers, $expectedProviders));

        // chain provider
        $this->assertEquals([new IteratorArgument([
            new Reference('security.user.provider.concrete.service'),
            new Reference('security.user.provider.concrete.basic'),
        ])], $container->getDefinition('security.user.provider.concrete.chain')->getArguments());
    }

    public function testFirewalls()
    {
        $container = $this->getContainer('container1');
        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $listeners = [];
        $configs = [];
        foreach (array_keys($arguments[1]->getValues()) as $contextId) {
            $contextDef = $container->getDefinition($contextId);
            $arguments = $contextDef->getArguments();
            $listeners[] = array_map('strval', $arguments['index_0']->getValues());

            $configDef = $container->getDefinition((string) $arguments['index_3']);
            $configs[] = array_values($configDef->getArguments());
        }

        // the IDs of the services are case sensitive or insensitive depending on
        // the Symfony version. Transform them to lowercase to simplify tests.
        $configs[0][2] = strtolower($configs[0][2]);
        $configs[2][2] = strtolower($configs[2][2]);

        $this->assertEquals([
            [
                'simple',
                'security.user_checker',
                '.security.request_matcher.xmi9dcw',
                false,
            ],
            [
                'secure',
                'security.user_checker',
                null,
                true,
                true,
                'security.user.provider.concrete.default',
                null,
                'security.authentication.form_entry_point.secure',
                null,
                null,
                [
                    'switch_user',
                    'x509',
                    'remote_user',
                    'form_login',
                    'http_basic',
                    'remember_me',
                    'anonymous',
                ],
                [
                    'parameter' => '_switch_user',
                    'role' => 'ROLE_ALLOWED_TO_SWITCH',
                    'stateless' => false,
                ],
            ],
            [
                'host',
                'security.user_checker',
                '.security.request_matcher.iw4hyjb',
                true,
                false,
                'security.user.provider.concrete.default',
                'host',
                'security.authentication.basic_entry_point.host',
                null,
                null,
                [
                    'http_basic',
                    'anonymous',
                ],
                null,
            ],
            [
                'with_user_checker',
                'app.user_checker',
                null,
                true,
                false,
                'security.user.provider.concrete.default',
                'with_user_checker',
                'security.authentication.basic_entry_point.with_user_checker',
                null,
                null,
                [
                    'http_basic',
                    'anonymous',
                ],
                null,
            ],
        ], $configs);

        $this->assertEquals([
            [],
            [
                'security.channel_listener',
                'security.authentication.listener.x509.secure',
                'security.authentication.listener.remote_user.secure',
                'security.authentication.listener.form.secure',
                'security.authentication.listener.basic.secure',
                'security.authentication.listener.rememberme.secure',
                'security.authentication.listener.anonymous.secure',
                'security.authentication.switchuser_listener.secure',
                'security.access_listener',
            ],
            [
                'security.channel_listener',
                'security.context_listener.0',
                'security.authentication.listener.basic.host',
                'security.authentication.listener.anonymous.host',
                'security.access_listener',
            ],
            [
                'security.channel_listener',
                'security.context_listener.1',
                'security.authentication.listener.basic.with_user_checker',
                'security.authentication.listener.anonymous.with_user_checker',
                'security.access_listener',
            ],
        ], $listeners);

        $this->assertFalse($container->hasAlias('Symfony\Component\Security\Core\User\UserCheckerInterface', 'No user checker alias is registered when custom user checker services are registered'));
    }

    public function testFirewallRequestMatchers()
    {
        $container = $this->getContainer('container1');

        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $matchers = [];

        foreach ($arguments[1]->getValues() as $reference) {
            if ($reference instanceof Reference) {
                $definition = $container->getDefinition((string) $reference);
                $matchers[] = $definition->getArguments();
            }
        }

        $this->assertEquals([
            [
                '/login',
            ],
            [
                '/test',
                'foo\\.example\\.org',
                ['GET', 'POST'],
            ],
        ], $matchers);
    }

    public function testUserCheckerAliasIsRegistered()
    {
        $container = $this->getContainer('no_custom_user_checker');

        $this->assertTrue($container->hasAlias('Symfony\Component\Security\Core\User\UserCheckerInterface', 'Alias for user checker is registered when no custom user checker service is registered'));
        $this->assertFalse($container->getAlias('Symfony\Component\Security\Core\User\UserCheckerInterface')->isPublic());
    }

    public function testAccess()
    {
        $container = $this->getContainer('container1');

        $rules = [];
        foreach ($container->getDefinition('security.access_map')->getMethodCalls() as $call) {
            if ('add' == $call[0]) {
                $rules[] = [(string) $call[1][0], $call[1][1], $call[1][2]];
            }
        }

        $matcherIds = [];
        foreach ($rules as list($matcherId, $attributes, $channel)) {
            $requestMatcher = $container->getDefinition($matcherId);

            $this->assertArrayNotHasKey($matcherId, $matcherIds);
            $matcherIds[$matcherId] = true;

            $i = \count($matcherIds);
            if (1 === $i) {
                $this->assertEquals(['ROLE_USER'], $attributes);
                $this->assertEquals('https', $channel);
                $this->assertEquals(
                    ['/blog/524', null, ['GET', 'POST'], [], [], null, 8000],
                    $requestMatcher->getArguments()
                );
            } elseif (2 === $i) {
                $this->assertEquals(['IS_AUTHENTICATED_ANONYMOUSLY'], $attributes);
                $this->assertNull($channel);
                $this->assertEquals(
                    ['/blog/.*'],
                    $requestMatcher->getArguments()
                );
            } elseif (3 === $i) {
                $this->assertEquals('IS_AUTHENTICATED_ANONYMOUSLY', $attributes[0]);
                $expression = $container->getDefinition((string) $attributes[1])->getArgument(0);
                $this->assertEquals("token.getUsername() matches '/^admin/'", $expression);
            }
        }
    }

    public function testMerge()
    {
        $container = $this->getContainer('merge');

        $this->assertEquals([
            'FOO' => ['MOO'],
            'ADMIN' => ['USER'],
        ], $container->getParameter('security.role_hierarchy.roles'));
    }

    public function testEncoders()
    {
        $container = $this->getContainer('container1');

        $this->assertEquals([[
            'JMS\FooBundle\Entity\User1' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => [false],
            ],
            'JMS\FooBundle\Entity\User2' => [
                'algorithm' => 'sha1',
                'encode_as_base64' => false,
                'iterations' => 5,
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User3' => [
                'algorithm' => 'md5',
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'encode_as_base64' => true,
                'iterations' => 5000,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User4' => new Reference('security.encoder.foo'),
            'JMS\FooBundle\Entity\User5' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => ['sha1', false, 5, 30],
            ],
            'JMS\FooBundle\Entity\User6' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\NativePasswordEncoder',
                'arguments' => [8, 102400, 15],
            ],
            'JMS\FooBundle\Entity\User7' => [
                'algorithm' => 'auto',
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'encode_as_base64' => true,
                'iterations' => 5000,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
        ]], $container->getDefinition('security.encoder_factory.generic')->getArguments());
    }

    public function testEncodersWithLibsodium()
    {
        if (!SodiumPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Libsodium is not available.');
        }

        $container = $this->getContainer('sodium_encoder');

        $this->assertEquals([[
            'JMS\FooBundle\Entity\User1' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => [false],
            ],
            'JMS\FooBundle\Entity\User2' => [
                'algorithm' => 'sha1',
                'encode_as_base64' => false,
                'iterations' => 5,
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User3' => [
                'algorithm' => 'md5',
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'encode_as_base64' => true,
                'iterations' => 5000,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User4' => new Reference('security.encoder.foo'),
            'JMS\FooBundle\Entity\User5' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => ['sha1', false, 5, 30],
            ],
            'JMS\FooBundle\Entity\User6' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\NativePasswordEncoder',
                'arguments' => [8, 102400, 15],
            ],
            'JMS\FooBundle\Entity\User7' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\SodiumPasswordEncoder',
                'arguments' => [8, 128 * 1024 * 1024],
            ],
        ]], $container->getDefinition('security.encoder_factory.generic')->getArguments());
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Configuring an encoder with "argon2i" as algorithm is deprecated since Symfony 4.3, use "auto" instead.
     */
    public function testEncodersWithArgon2i()
    {
        if (!Argon2iPasswordEncoder::isSupported()) {
            $this->markTestSkipped('Argon2i algorithm is not supported.');
        }

        $container = $this->getContainer('argon2i_encoder');

        $this->assertEquals([[
            'JMS\FooBundle\Entity\User1' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => [false],
            ],
            'JMS\FooBundle\Entity\User2' => [
                'algorithm' => 'sha1',
                'encode_as_base64' => false,
                'iterations' => 5,
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User3' => [
                'algorithm' => 'md5',
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'encode_as_base64' => true,
                'iterations' => 5000,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User4' => new Reference('security.encoder.foo'),
            'JMS\FooBundle\Entity\User5' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => ['sha1', false, 5, 30],
            ],
            'JMS\FooBundle\Entity\User6' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\NativePasswordEncoder',
                'arguments' => [8, 102400, 15],
            ],
            'JMS\FooBundle\Entity\User7' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\Argon2iPasswordEncoder',
                'arguments' => [256, 1, 2],
            ],
        ]], $container->getDefinition('security.encoder_factory.generic')->getArguments());
    }

    /**
     * @group legacy
     */
    public function testEncodersWithBCrypt()
    {
        $container = $this->getContainer('bcrypt_encoder');

        $this->assertEquals([[
            'JMS\FooBundle\Entity\User1' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\PlaintextPasswordEncoder',
                'arguments' => [false],
            ],
            'JMS\FooBundle\Entity\User2' => [
                'algorithm' => 'sha1',
                'encode_as_base64' => false,
                'iterations' => 5,
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User3' => [
                'algorithm' => 'md5',
                'hash_algorithm' => 'sha512',
                'key_length' => 40,
                'ignore_case' => false,
                'encode_as_base64' => true,
                'iterations' => 5000,
                'cost' => null,
                'memory_cost' => null,
                'time_cost' => null,
                'threads' => null,
            ],
            'JMS\FooBundle\Entity\User4' => new Reference('security.encoder.foo'),
            'JMS\FooBundle\Entity\User5' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\Pbkdf2PasswordEncoder',
                'arguments' => ['sha1', false, 5, 30],
            ],
            'JMS\FooBundle\Entity\User6' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\NativePasswordEncoder',
                'arguments' => [8, 102400, 15],
            ],
            'JMS\FooBundle\Entity\User7' => [
                'class' => 'Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder',
                'arguments' => [15],
            ],
        ]], $container->getDefinition('security.encoder_factory.generic')->getArguments());
    }

    public function testRememberMeThrowExceptionsDefault()
    {
        $container = $this->getContainer('container1');
        $this->assertTrue($container->getDefinition('security.authentication.listener.rememberme.secure')->getArgument(5));
    }

    public function testRememberMeThrowExceptions()
    {
        $container = $this->getContainer('remember_me_options');
        $service = $container->getDefinition('security.authentication.listener.rememberme.main');
        $this->assertEquals('security.authentication.rememberme.services.persistent.main', $service->getArgument(1));
        $this->assertFalse($service->getArgument(5));
    }

    public function testUserCheckerConfig()
    {
        $this->assertEquals('app.user_checker', $this->getContainer('container1')->getAlias('security.user_checker.with_user_checker'));
    }

    public function testUserCheckerConfigWithDefaultChecker()
    {
        $this->assertEquals('security.user_checker', $this->getContainer('container1')->getAlias('security.user_checker.host'));
    }

    public function testUserCheckerConfigWithNoCheckers()
    {
        $this->assertEquals('security.user_checker', $this->getContainer('container1')->getAlias('security.user_checker.secure'));
    }

    public function testUserPasswordEncoderCommandIsRegistered()
    {
        $this->assertTrue($this->getContainer('remember_me_options')->has('security.command.user_password_encoder'));
    }

    public function testDefaultAccessDecisionManagerStrategyIsAffirmative()
    {
        $container = $this->getContainer('access_decision_manager_default_strategy');

        $this->assertSame(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $container->getDefinition('security.access.decision_manager')->getArgument(1), 'Default vote strategy is affirmative');
    }

    public function testCustomAccessDecisionManagerService()
    {
        $container = $this->getContainer('access_decision_manager_service');

        $this->assertSame('app.access_decision_manager', (string) $container->getAlias('security.access.decision_manager'), 'The custom access decision manager service is aliased');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid configuration for path "security.access_decision_manager": "strategy" and "service" cannot be used together.
     */
    public function testAccessDecisionManagerServiceAndStrategyCannotBeUsedAtTheSameTime()
    {
        $this->getContainer('access_decision_manager_service_and_strategy');
    }

    public function testAccessDecisionManagerOptionsAreNotOverriddenByImplicitStrategy()
    {
        $container = $this->getContainer('access_decision_manager_customized_config');

        $accessDecisionManagerDefinition = $container->getDefinition('security.access.decision_manager');

        $this->assertSame(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $accessDecisionManagerDefinition->getArgument(1));
        $this->assertTrue($accessDecisionManagerDefinition->getArgument(2));
        $this->assertFalse($accessDecisionManagerDefinition->getArgument(3));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid firewall "main": user provider "undefined" not found.
     */
    public function testFirewallUndefinedUserProvider()
    {
        $this->getContainer('firewall_undefined_provider');
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage Invalid firewall "main": user provider "undefined" not found.
     */
    public function testFirewallListenerUndefinedProvider()
    {
        $this->getContainer('listener_undefined_provider');
    }

    public function testFirewallWithUserProvider()
    {
        $this->getContainer('firewall_provider');
        $this->addToAssertionCount(1);
    }

    public function testFirewallListenerWithProvider()
    {
        $this->getContainer('listener_provider');
        $this->addToAssertionCount(1);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "simple_form" security listener is deprecated Symfony 4.2, use Guard instead.
     */
    public function testSimpleAuth()
    {
        $container = $this->getContainer('simple_auth');
        $arguments = $container->getDefinition('security.firewall.map')->getArguments();
        $listeners = [];
        $configs = [];
        foreach (array_keys($arguments[1]->getValues()) as $contextId) {
            $contextDef = $container->getDefinition($contextId);
            $arguments = $contextDef->getArguments();
            $listeners[] = array_map('strval', $arguments['index_0']->getValues());

            $configDef = $container->getDefinition((string) $arguments['index_3']);
            $configs[] = array_values($configDef->getArguments());
        }

        $this->assertSame([[
            'simple_auth',
            'security.user_checker',
            null,
            true,
            false,
            'security.user.provider.concrete.default',
            'simple_auth',
            'security.authentication.form_entry_point.simple_auth',
            null,
            null,
            ['simple_form', 'anonymous',
            ],
            null,
        ]], $configs);

        $this->assertSame([[
            'security.channel_listener',
            'security.context_listener.0',
            'security.authentication.listener.simple_form.simple_auth',
            'security.authentication.listener.anonymous.simple_auth',
            'security.access_listener',
        ]], $listeners);
    }

    /**
     * @group legacy
     * @expectedDeprecation Normalization of cookie names is deprecated since Symfony 4.3. Starting from Symfony 5.0, the "cookie1-name" cookie configured in "logout.delete_cookies" will delete the "cookie1-name" cookie instead of the "cookie1_name" cookie.
     * @expectedDeprecation Normalization of cookie names is deprecated since Symfony 4.3. Starting from Symfony 5.0, the "cookie3-long_name" cookie configured in "logout.delete_cookies" will delete the "cookie3-long_name" cookie instead of the "cookie3_long_name" cookie.
     */
    public function testLogoutDeleteCookieNamesNormalization()
    {
        $container = $this->getContainer('logout_delete_cookies');
        $cookiesToDelete = $container->getDefinition('security.logout.handler.cookie_clearing.main')->getArgument(0);
        $expectedCookieNames = ['cookie2_name', 'cookie1_name', 'cookie3_long_name'];

        $this->assertSame($expectedCookieNames, array_keys($cookiesToDelete));
    }

    protected function getContainer($file)
    {
        $file .= '.'.$this->getFileExtension();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);

        $security = new SecurityExtension();
        $container->registerExtension($security);

        $bundle = new SecurityBundle();
        $bundle->build($container); // Attach all default factories
        $this->getLoader($container)->load($file);

        $container->getCompilerPassConfig()->setOptimizationPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        $container->compile();

        return $container;
    }
}
