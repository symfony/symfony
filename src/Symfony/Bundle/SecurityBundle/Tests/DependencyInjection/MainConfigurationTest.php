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

use Symfony\Bundle\SecurityBundle\DependencyInjection\MainConfiguration;
use Symfony\Component\Config\Definition\Processor;

class MainConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * The minimal, required config needed to not have any required validation
     * issues.
     *
     * @var array
     */
    protected static $minimalConfig = array(
        'providers' => array(
            'stub' => array(
                'id' => 'foo',
            ),
        ),
        'firewalls' => array(
            'stub' => array(),
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
        $config = $processor->processConfiguration($configuration, array($config));
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
        $config = $processor->processConfiguration($configuration, array($config));
    }

    public function testCsrfAliases()
    {
        $config = array(
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_provider' => 'a_token_generator',
                        'intention' => 'a_token_id',
                    ),
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testCsrfOriginalAndAliasValueCausesException()
    {
        $config = array(
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_id' => 'a_token_id',
                        'intention' => 'old_name',
                    ),
                ),
            ),
        );
        $config = array_merge(static::$minimalConfig, $config);

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $processedConfig = $processor->processConfiguration($configuration, array($config));
    }
}
