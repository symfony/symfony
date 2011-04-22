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
            'stub' => array(),
        ),
        'firewalls' => array(
            'stub' => array(),
        ),
    );

    /**
     * Test that the main tree is OK to be passed a factory or factories
     * key, without throwing any validation errors.
     */
    public function testMainConfigTreeWithFactories()
    {
        $config = array_merge(self::$minimalConfig, array(
            'factory'   => array('foo' => 'bar'),
            'factories' => array('lorem' => 'ipsum'),
        ));

        $processor = new Processor();
        $configuration = new MainConfiguration(array());
        $config = $processor->processConfiguration($configuration, array($config));

        $this->assertFalse(array_key_exists('factory', $config), 'The factory key is silently removed without an exception');
        $this->assertEquals(array(), $config['factories'], 'The factories key is just an empty array');
    }
}
