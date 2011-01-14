<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TwigExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $container = new ContainerBuilder();
        $loader = new TwigExtension();

        $loader->configLoad(array(array()), $container);
        $this->assertEquals('Twig_Environment', $container->getParameter('twig.class'), '->configLoad() loads the twig.xml file if not already loaded');

        $loader->configLoad(array(array('charset' => 'ISO-8859-1')), $container);
        $options = $container->getParameter('twig.options');
        $this->assertEquals('ISO-8859-1', $options['charset'], '->configLoad() overrides existing configuration options');
        $this->assertEquals('%kernel.debug%', $options['debug'], '->configLoad() merges the new values with the old ones');
    }

    public function testConfigGlobals()
    {
        // XML
        $container = new ContainerBuilder();
        $loader = new TwigExtension();
        $loader->configLoad(array(array('global' => array(
            array('key' => 'foo', 'type' => 'service', 'id' => 'bar'),
            array('key' => 'pi', 'value' => 3.14),
        ))), $container);
        $config = $container->getDefinition('twig')->getMethodCalls();
        $this->assertEquals('foo', $config[0][1][0]);
        $this->assertEquals(new Reference('bar'), $config[0][1][1]);
        $this->assertEquals('pi', $config[1][1][0]);
        $this->assertEquals(3.14, $config[1][1][1]);

        // YAML, PHP
        $container = new ContainerBuilder();
        $loader = new TwigExtension();
        $loader->configLoad(array(array('globals' => array(
            'foo' => '@bar',
            'pi'  => 3.14,
        ))), $container);
        $config = $container->getDefinition('twig')->getMethodCalls();
        $this->assertEquals('foo', $config[0][1][0]);
        $this->assertEquals(new Reference('bar'), $config[0][1][1]);
        $this->assertEquals('pi', $config[1][1][0]);
        $this->assertEquals(3.14, $config[1][1][1]);
    }

    public function testConfigExtensions()
    {
        // XML
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass');
        $loader = new TwigExtension();
        $loader->configLoad(array(array('extensions' => array(array('id' => 'foo')))), $container);
        $config = $container->getDefinition('foo');
        $this->assertEquals(array('twig.extension'), array_keys($config->getTags()));

        // YAML, PHP
        $container = new ContainerBuilder();
        $container->register('foo', 'stdClass');
        $loader = new TwigExtension();
        $loader->configLoad(array(array('extensions' => array('foo'))), $container);
        $config = $container->getDefinition('foo');
        $this->assertEquals(array('twig.extension'), array_keys($config->getTags()));
    }
}
