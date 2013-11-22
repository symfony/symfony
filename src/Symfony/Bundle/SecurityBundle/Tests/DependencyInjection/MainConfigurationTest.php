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

    public function testConfigForCsrfTokenGenerator()
    {
        $config = array(
            'providers' => array(
                'stub' => array(
                    'id' => 'foo',
                ),
            ),
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_generator' => 'bar'
                    )
                )
            )
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $config = $processor->processConfiguration($configuration, array($config));

        $this->assertTrue(isset($config['firewalls']['stub']['logout']['csrf_provider']));
        $this->assertEquals($config['firewalls']['stub']['logout']['csrf_provider'], 'bar');
        $this->assertFalse(isset($config['firewalls']['stub']['logout']['csrf_token_generator']));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testBothConfigForCsrfTokenGeneratorAndCsrfProvider()
    {
        $config = array(
            'providers' => array(
                'stub' => array(
                    'id' => 'foo',
                ),
            ),
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_generator' => 'bar',
                        'csrf_provider' => 'baz'
                    )
                )
            )
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $config = $processor->processConfiguration($configuration, array($config));
    }

    public function testConfigForCsrfTokenId()
    {
        $config = array(
            'providers' => array(
                'stub' => array(
                    'id' => 'foo',
                ),
            ),
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_id' => 'bar'
                    )
                )
            )
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $config = $processor->processConfiguration($configuration, array($config));

        $this->assertTrue(isset($config['firewalls']['stub']['logout']['intention']));
        $this->assertEquals($config['firewalls']['stub']['logout']['intention'], 'bar');
        $this->assertFalse(isset($config['firewalls']['stub']['logout']['csrf_token_id']));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testBothConfigForCsrfTokenIdAndIntention()
    {
        $config = array(
            'providers' => array(
                'stub' => array(
                    'id' => 'foo',
                ),
            ),
            'firewalls' => array(
                'stub' => array(
                    'logout' => array(
                        'csrf_token_id' => 'bar',
                        'intention' => 'baz'
                    )
                )
            )
        );

        $processor = new Processor();
        $configuration = new MainConfiguration(array(), array());
        $config = $processor->processConfiguration($configuration, array($config));
    }
}
