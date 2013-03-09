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

class ConfigurationTest extends \PHPUnit_Framework_TestCase
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
}
