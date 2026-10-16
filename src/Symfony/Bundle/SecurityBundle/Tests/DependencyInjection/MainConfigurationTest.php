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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Security\Http\Authentication\ExposeSecurityLevel;

class MainConfigurationTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    /**
     * The minimal, required config needed to not have any required validation
     * issues.
     */
    protected static array $minimalConfig = [
        'providers' => [
            'stub' => [
                'id' => 'foo',
            ],
        ],
        'firewalls' => [
            'stub' => [],
        ],
    ];

    public function testNoConfigForProvider()
    {
        $config = [
            'providers' => [
                'stub' => [],
            ],
        ];

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testManyConfigForProvider()
    {
        $config = [
            'providers' => [
                'stub' => [
                    'id' => 'foo',
                    'chain' => [],
                ],
            ],
        ];

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testCsrfAliases()
    {
        $config = [
            'firewalls' => [
                'stub' => [
                    'logout' => [
                        'csrf_token_manager' => 'a_token_manager',
                        'csrf_token_id' => 'a_token_id',
                    ],
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);
        $this->assertArrayHasKey('csrf_token_manager', $processedConfig['firewalls']['stub']['logout']);
        $this->assertEquals('a_token_manager', $processedConfig['firewalls']['stub']['logout']['csrf_token_manager']);
        $this->assertArrayHasKey('csrf_token_id', $processedConfig['firewalls']['stub']['logout']);
        $this->assertEquals('a_token_id', $processedConfig['firewalls']['stub']['logout']['csrf_token_id']);
    }

    public function testLogoutCsrf()
    {
        $config = [
            'firewalls' => [
                'custom_token_manager' => [
                    'logout' => [
                        'csrf_token_manager' => 'a_token_manager',
                        'csrf_token_id' => 'a_token_id',
                    ],
                ],
                'default_token_manager' => [
                    'logout' => [
                        'enable_csrf' => true,
                        'csrf_token_id' => 'a_token_id',
                    ],
                ],
                'disabled_csrf' => [
                    'logout' => [
                        'enable_csrf' => false,
                    ],
                ],
                'empty' => [
                    'logout' => true,
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $assertions = [
            'custom_token_manager' => [true, 'a_token_manager'],
            'default_token_manager' => [true, 'security.csrf.token_manager'],
            'disabled_csrf' => [false, null],
            'empty' => [false, null],
        ];
        foreach ($assertions as $firewallName => [$enabled, $tokenManager]) {
            $this->assertEquals($enabled, $processedConfig['firewalls'][$firewallName]['logout']['enable_csrf']);
            if ($tokenManager) {
                $this->assertEquals($tokenManager, $processedConfig['firewalls'][$firewallName]['logout']['csrf_token_manager']);
                $this->assertEquals('a_token_id', $processedConfig['firewalls'][$firewallName]['logout']['csrf_token_id']);
            } else {
                $this->assertArrayNotHasKey('csrf_token_manager', $processedConfig['firewalls'][$firewallName]['logout']);
            }
        }
    }

    public function testSwitchUserPathCannotBeEmpty()
    {
        $config = array_merge(static::$minimalConfig, [
            'firewalls' => [
                'main' => [
                    'switch_user' => ['path' => ''],
                ],
            ],
        ]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "security.firewalls.main.switch_user.path" cannot contain an empty value, but got "".');

        (new Processor())->processConfiguration(new MainConfiguration([], []), [$config]);
    }

    public function testLogoutDeleteCookies()
    {
        $config = [
            'firewalls' => [
                'stub' => [
                    'logout' => [
                        'delete_cookies' => [
                            'my_cookie' => [
                                'path' => '/',
                                'domain' => 'example.org',
                                'secure' => true,
                                'samesite' => 'none',
                                'partitioned' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);
        $this->assertArrayHasKey('delete_cookies', $processedConfig['firewalls']['stub']['logout']);
        $deleteCookies = $processedConfig['firewalls']['stub']['logout']['delete_cookies'];
        $this->assertSame('/', $deleteCookies['my_cookie']['path']);
        $this->assertSame('example.org', $deleteCookies['my_cookie']['domain']);
        $this->assertTrue($deleteCookies['my_cookie']['secure']);
        $this->assertSame('none', $deleteCookies['my_cookie']['samesite']);
        $this->assertTrue($deleteCookies['my_cookie']['partitioned']);
    }

    public function testDefaultUserCheckers()
    {
        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [static::$minimalConfig]);

        $this->assertEquals('security.user_checker', $processedConfig['firewalls']['stub']['user_checker']);
    }

    public function testUserCheckers()
    {
        $config = [
            'firewalls' => [
                'stub' => [
                    'user_checker' => 'app.henk_checker',
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertEquals('app.henk_checker', $processedConfig['firewalls']['stub']['user_checker']);
    }

    public function testConfigMergeWithAccessDecisionManager()
    {
        $config = [
            'access_decision_manager' => [
                'strategy' => MainConfiguration::STRATEGY_UNANIMOUS,
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $config2 = [];

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config, $config2]);

        $this->assertSame(MainConfiguration::STRATEGY_UNANIMOUS, $processedConfig['access_decision_manager']['strategy']);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testEraseCredentialsDeprecation()
    {
        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.1: Setting the "security.erase_credentials" configuration option is deprecated. It will be removed in Symfony 9.0, as the "eraseCredentials()" method was removed in Symfony 8.0.');

        $config = array_merge(static::$minimalConfig, ['erase_credentials' => false]);
        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processor->processConfiguration($configuration, [$config]);
    }

    public function testFirewalls()
    {
        $factory = $this->createMock(AuthenticatorFactoryInterface::class);
        $factory->expects($this->once())->method('addConfiguration');
        $factory->method('getKey')->willReturn('key');

        $configuration = new MainConfiguration(['stub' => $factory], []);
        $configuration->getConfigTreeBuilder();
    }

    #[DataProvider('provideHideUserNotFoundData')]
    public function testExposeSecurityErrors(array $config, ExposeSecurityLevel $expectedExposeSecurityErrors)
    {
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertEquals($expectedExposeSecurityErrors, $processedConfig['expose_security_errors']);
        $this->assertArrayNotHasKey('hide_user_not_found', $processedConfig);
    }

    public static function provideHideUserNotFoundData(): iterable
    {
        yield [[], ExposeSecurityLevel::None];
        yield [['expose_security_errors' => ExposeSecurityLevel::None], ExposeSecurityLevel::None];
        yield [['expose_security_errors' => ExposeSecurityLevel::AccountStatus], ExposeSecurityLevel::AccountStatus];
        yield [['expose_security_errors' => ExposeSecurityLevel::All], ExposeSecurityLevel::All];
        yield [['expose_security_errors' => 'none'], ExposeSecurityLevel::None];
        yield [['expose_security_errors' => 'account_status'], ExposeSecurityLevel::AccountStatus];
        yield [['expose_security_errors' => 'all'], ExposeSecurityLevel::All];
    }

    public function testClearSiteDataDirectivesAcceptAllSupportedValues()
    {
        $directives = ['*', 'cache', 'cookies', 'storage', 'clientHints', 'executionContexts', 'prefetchCache', 'prerenderCache'];
        $config = array_merge(static::$minimalConfig, [
            'firewalls' => [
                'stub' => [
                    'logout' => [
                        'clear_site_data' => $directives,
                    ],
                ],
            ],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame($directives, $processedConfig['firewalls']['stub']['logout']['clear_site_data']);
    }

    public function testClearSiteDataDirectivesAcceptStringList()
    {
        $config = array_merge(static::$minimalConfig, [
            'firewalls' => [
                'stub' => [
                    'logout' => [
                        'clear_site_data' => 'clientHints, prefetchCache, prerenderCache',
                    ],
                ],
            ],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame(['clientHints', 'prefetchCache', 'prerenderCache'], $processedConfig['firewalls']['stub']['logout']['clear_site_data']);
    }

    public function testClearSiteDataDirectivesRejectsUnknownValue()
    {
        $config = array_merge(static::$minimalConfig, [
            'firewalls' => [
                'stub' => [
                    'logout' => [
                        'clear_site_data' => ['unknown_directive'],
                    ],
                ],
            ],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration($configuration, [$config]);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[DataProvider('provideAccessControlRuleRoles')]
    public function testConfiguringManyAccessControlRuleRolesIsDeprecated(array|string $roles, array $resultingRoles)
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'roles' => $roles]],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Configuring an access control rule with many "roles" is deprecated, use "allow_if" or role hierarchy instead.');

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertEqualsCanonicalizing($resultingRoles, $processedConfig['access_control'][0]['roles']);
    }

    public static function provideAccessControlRuleRoles(): iterable
    {
        yield [['ROLE_ADMIN', 'ROLE_USER'], ['ROLE_ADMIN', 'ROLE_USER']];
        yield ['ROLE_ADMIN, ROLE_USER', ['ROLE_ADMIN', 'ROLE_USER']];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testConfiguringAccessControlRuleRolesAndAllowIfIsDeprecated()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [[
                'path' => '/',
                'roles' => 'ROLE_USER',
                'allow_if' => 'is_granted("ROLE_ADMIN")',
            ]],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Configuring both an access control rule "allow_if" and "roles" is deprecated, update "allow_if" instead.');

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testAccessControlRuleIsGranted()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'is_granted' => 'ROLE_ADMIN']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame('ROLE_ADMIN', $processedConfig['access_control'][0]['is_granted']);
    }

    public function testAccessControlRuleIsGrantedRejectsManyAttributes()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'is_granted' => ['ROLE_ADMIN', 'ROLE_USER']]],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage('Expected "string", but got "array".');

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testAccessControlRuleIsGrantedRejectsCommaSeparatedAttributes()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'is_granted' => 'ROLE_ADMIN, ROLE_USER']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "is_granted" option of an access control rule takes a single attribute (got "ROLE_ADMIN, ROLE_USER"), use "allow_if" or role hierarchy for many.');

        $processor->processConfiguration($configuration, [$config]);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    public function testAccessControlRuleIsGrantedCannotBeEmpty(?string $isGranted)
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'is_granted' => $isGranted]],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The path "security.access_control.0.is_granted" cannot contain an empty value, but got "".');

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testAccessControlRuleIsGrantedAndAllowIfAreExclusive()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', 'is_granted' => 'ROLE_ADMIN', 'allow_if' => 'request.isSecure()']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "is_granted" and "allow_if" options of an access control rule cannot be used together, call "is_granted()" in the "allow_if" expression instead.');

        $processor->processConfiguration($configuration, [$config]);
    }

    public function testAccessControlRuleRequestAttributes()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['request_attributes' => ['_route' => 'admin', '_format' => 'json'], 'is_granted' => 'ROLE_ADMIN']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame(['_route' => 'admin', '_format' => 'json'], $processedConfig['access_control'][0]['request_attributes']);
    }

    public function testAccessControlRuleRequestAttributesKeepDashesInNames()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['request_attributes' => ['x-tenant' => 'acme'], 'is_granted' => 'ROLE_ADMIN']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame(['x-tenant' => 'acme'], $processedConfig['access_control'][0]['request_attributes']);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[DataProvider('provideAccessControlRuleRolesKeys')]
    public function testConfiguringAccessControlRuleRolesIsDeprecated(string $key)
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/', $key => 'ROLE_USER']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Configuring an access control rule with "roles" is deprecated, use "is_granted" for a single attribute, or "allow_if" otherwise.');

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame(['ROLE_USER'], $processedConfig['access_control'][0]['roles']);
    }

    public static function provideAccessControlRuleRolesKeys(): iterable
    {
        yield ['roles'];
        yield ['role'];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testConfiguringAccessControlRuleAttributesIsDeprecated()
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['attributes' => ['_route' => 'admin'], 'is_granted' => 'ROLE_ADMIN']],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectUserDeprecationMessage('Since symfony/security-bundle 8.2: Configuring an access control rule with "attributes" is deprecated, use "request_attributes" instead.');

        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertSame(['_route' => 'admin'], $processedConfig['access_control'][0]['attributes']);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[DataProvider('provideAccessControlRuleRenamedOptions')]
    public function testAccessControlRuleRejectsRenamedOptionAlongsideItsReplacement(array $rule, string $message)
    {
        $config = array_merge(static::$minimalConfig, [
            'access_control' => [['path' => '/'] + $rule],
        ]);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $processor->processConfiguration($configuration, [$config]);
    }

    public static function provideAccessControlRuleRenamedOptions(): iterable
    {
        yield 'is_granted and roles' => [
            ['is_granted' => 'ROLE_ADMIN', 'roles' => 'ROLE_USER'],
            'The "is_granted" and "roles" options of an access control rule cannot be used together, use "is_granted" only.',
        ];
        yield 'request_attributes and attributes' => [
            ['request_attributes' => ['_format' => 'json'], 'attributes' => ['_route' => 'admin']],
            'The "request_attributes" and "attributes" options of an access control rule cannot be used together, use "request_attributes" only.',
        ];
    }
}
