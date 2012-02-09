<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\Tests\DependencyInjection;

use Symfony\Bundle\MonologBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Some basic tests to make sure the configuration is correctly processed in
     * the standard case.
     */
    public function testProcessSimpleCase()
    {
        $configs = array(
            array(
                'handlers' => array('foobar' => array('type' => 'stream', 'path' => '/foo/bar'))
            )
        );

        $config = $this->process($configs);

        $this->assertArrayHasKey('handlers', $config);
        $this->assertArrayHasKey('foobar', $config['handlers']);
        $this->assertEquals('stream',   $config['handlers']['foobar']['type']);
        $this->assertEquals('/foo/bar', $config['handlers']['foobar']['path']);
    }

    public function provideProcessStringChannels()
    {
        return array(
            array('foo',  'foo', true),
            array('!foo', 'foo', false)
        );
    }

    /**
     * @dataProvider provideProcessStringChannels
     */
    public function testProcessStringChannels($string, $expectedString, $isInclusive)
    {
        $configs = array(
            array(
                'handlers' => array(
                    'foobar' => array(
                        'type' =>     'stream',
                        'path' =>     '/foo/bar',
                        'channels' => $string
                    )
                )
            )
        );

        $config = $this->process($configs);

        $this->assertEquals($isInclusive ? 'inclusive' : 'exclusive', $config['handlers']['foobar']['channels']['type']);
        $this->assertCount(1, $config['handlers']['foobar']['channels']['elements']);
        $this->assertEquals($expectedString, $config['handlers']['foobar']['channels']['elements'][0]);
    }

    public function testArrays()
    {
        $configs = array(
            array(
                'handlers' => array(
                    'foo' => array(
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => array('A', 'B')
                    ),
                    'bar' => array(
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => array('!C', '!D')
                    ),
                )
            )
        );

        $config = $this->process($configs);

        // Check foo
        $this->assertCount(2, $config['handlers']['foo']['channels']['elements']);
        $this->assertEquals('inclusive', $config['handlers']['foo']['channels']['type']);
        $this->assertEquals('A', $config['handlers']['foo']['channels']['elements'][0]);
        $this->assertEquals('B', $config['handlers']['foo']['channels']['elements'][1]);
        // Check bar
        $this->assertCount(2, $config['handlers']['bar']['channels']['elements']);
        $this->assertEquals('exclusive', $config['handlers']['bar']['channels']['type']);
        $this->assertEquals('C', $config['handlers']['bar']['channels']['elements'][0]);
        $this->assertEquals('D', $config['handlers']['bar']['channels']['elements'][1]);
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidArrays()
    {
        $configs = array(
            array(
                'handlers' => array(
                    'foo' => array(
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => array('A', '!B')
                    )
                )
            )
        );

        $config = $this->process($configs);
    }

    public function testWithType()
    {
        $configs = array(
            array(
                'handlers' => array(
                    'foo' => array(
                        'type' => 'stream',
                        'path' => '/foo',
                        'channels' => array(
                            'type' => 'inclusive',
                            'elements' => array('A', 'B')
                        )
                    )
                )
            )
        );

        $config = $this->process($configs);

        // Check foo
        $this->assertCount(2, $config['handlers']['foo']['channels']['elements']);
        $this->assertEquals('inclusive', $config['handlers']['foo']['channels']['type']);
        $this->assertEquals('A', $config['handlers']['foo']['channels']['elements'][0]);
        $this->assertEquals('B', $config['handlers']['foo']['channels']['elements'][1]);
    }

    /**
     * Processes an array of configurations and returns a compiled version.
     *
     * @param array $configs An array of raw configurations
     *
     * @return array A normalized array
     */
    protected function process($configs)
    {
        $configuration = new Configuration();
        $processor = new Processor();
        return $processor->process($configuration->getConfigTreeBuilder()->buildTree(), $configs);
    }
}
