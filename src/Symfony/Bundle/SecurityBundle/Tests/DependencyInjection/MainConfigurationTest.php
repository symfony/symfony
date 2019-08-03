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
            'logout_on_user_change' => true,
        ],
    ];

    public function testNoConfigForProvider()
    {
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
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
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
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
                        'csrf_token_generator' => 'a_token_generator',
                        'csrf_token_id' => 'a_token_id',
                    ],
                    'logout_on_user_change' => true,
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);
        $this->assertArrayHasKey('csrf_token_generator', $processedConfig['firewalls']['stub']['logout']);
        $this->assertEquals('a_token_generator', $processedConfig['firewalls']['stub']['logout']['csrf_token_generator']);
        $this->assertArrayHasKey('csrf_token_id', $processedConfig['firewalls']['stub']['logout']);
        $this->assertEquals('a_token_id', $processedConfig['firewalls']['stub']['logout']['csrf_token_id']);
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
                    'logout_on_user_change' => true,
                ],
            ],
        ];
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration([], []);
        $processedConfig = $processor->processConfiguration($configuration, [$config]);

        $this->assertEquals('app.henk_checker', $processedConfig['firewalls']['stub']['user_checker']);
    }
}
