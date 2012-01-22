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
    public function testSimpleCase()
    {
        $configs = array(
            array(
                'handlers' => array('foobar' => array('type' => 'stream', 'path' => '/foo/bar'))
            )
        );
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->process($configuration->getConfigTreeBuilder()->buildTree(), $configs);

        // Some basic test to make sure the configuration was correctly processed
        $this->assertArrayHasKey('handlers', $config);
        $this->assertArrayHasKey('foobar', $config['handlers']);
        $this->assertEquals('stream',   $config['handlers']['foobar']['type']);
        $this->assertEquals('/foo/bar', $config['handlers']['foobar']['path']);
    }
}
