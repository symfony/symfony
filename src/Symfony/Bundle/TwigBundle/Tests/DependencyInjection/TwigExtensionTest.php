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

class TwigExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $container = new ContainerBuilder();
        $loader = new TwigExtension();

        $loader->configLoad(array(), $container);
        $this->assertEquals('Twig_Environment', $container->getParameter('twig.class'), '->configLoad() loads the twig.xml file if not already loaded');

        $loader->configLoad(array('charset' => 'ISO-8859-1'), $container);
        $options = $container->getParameter('twig.options');
        $this->assertEquals('ISO-8859-1', $options['charset'], '->configLoad() overrides existing configuration options');
        $this->assertEquals('%kernel.debug%', $options['debug'], '->configLoad() merges the new values with the old ones');
    }

    public function testConfigGlobals()
    {
        // XML
        $container = new ContainerBuilder();
        $loader = new TwigExtension();
        $loader->configLoad(array('global' => array(array('key' => 'foo', 'id' => 'bar'))), $container);
        $config = $container->getDefinition('twig')->getMethodCalls();
        $this->assertEquals('foo', $config[0][1][0]);
        $this->assertEquals('bar', (string) $config[0][1][1]);

        // YAML, PHP
        $container = new ContainerBuilder();
        $loader = new TwigExtension();
        $loader->configLoad(array('globals' => array('foo' => 'bar')), $container);
        $config = $container->getDefinition('twig')->getMethodCalls();
        $this->assertEquals('foo', $config[0][1][0]);
        $this->assertEquals('bar', (string) $config[0][1][1]);
    }
}
