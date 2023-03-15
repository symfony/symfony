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
use Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class MainConfigurationTest extends TestCase
{
    /**
     * The minimal, required config needed to not have any required validation
     * issues.
     */
    protected static $minimalConfig = [
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
        $this->expectException(InvalidConfigurationException::class);
        $config = [
            'providers' => [
                'stub' => [],
            ],
        ];

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processor->processConfiguration($configuration, [$config]);
    }

    public function testManyConfigForProvider()
    {
        $this->expectException(InvalidConfigurationException::class);
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

    public function testFirewalls()
    {
        $factory = $this->createMock(AuthenticatorFactoryInterface::class);
        $factory->expects($this->once())->method('addConfiguration');
        $factory->method('getKey')->willReturn('key');

        $configuration = new MainConfiguration(['stub' => $factory], []);
        $configuration->getConfigTreeBuilder();
    }
}
