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
    protected static $minimalConfig = array(
        'providers' => array(
            'stub' => array(
                'id' => 'foo',
            ),
        ),
        'firewalls' => array(
            'stub' => array(),
            'logout_on_user_change' => true,
        ),
    );

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testNoConfigForProvider()
    {
        $config = array(
            'providers' => array(
                'stub' => array(),
            ),
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processor->processConfiguration($configuration, array($config));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testManyConfigForProvider()
    {
        $config = array(
            'providers' => array(
                'stub' => array(
                    'id' => 'foo',
                    'chain' => array(),
                ),
            ),
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processor->processConfiguration($configuration, array($config));
    }

    public function testCsrfAliases()
    {
        $config = array(
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_generator' => 'a_token_generator',
                        'csrf_token_id' => 'a_token_id',
                    ),
                    'logout_on_user_change' => true,
                ),
            ),
        );
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processedConfig = $processor->processConfiguration($configuration, array($config));
        $this->assertTrue(isset($processedConfig['firewalls']['stub']['logout']['csrf_token_generator']));
        $this->assertEquals('a_token_generator', $processedConfig['firewalls']['stub']['logout']['csrf_token_generator']);
        $this->assertTrue(isset($processedConfig['firewalls']['stub']['logout']['csrf_token_id']));
        $this->assertEquals('a_token_id', $processedConfig['firewalls']['stub']['logout']['csrf_token_id']);
    }

    public function testDefaultUserCheckers()
    {
        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processedConfig = $processor->processConfiguration($configuration, array(static::$minimalConfig));

        $this->assertEquals('security.user_checker', $processedConfig['firewalls']['stub']['user_checker']);
    }

    public function testUserCheckers()
    {
        $config = array(
            'firewalls' => array(
                'stub' => array(
                    'user_checker' => 'app.henk_checker',
                    'logout_on_user_change' => true,
                ),
            ),
        );
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processedConfig = $processor->processConfiguration($configuration, array($config));

        $this->assertEquals('app.henk_checker', $processedConfig['firewalls']['stub']['user_checker']);
    }
}
