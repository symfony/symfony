<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\PropelBundle\Tests\DependencyInjection;

use Symfony\Framework\PropelBundle\Tests\TestCase;
use Symfony\Framework\PropelBundle\DependencyInjection\PropelExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class PropelExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new PropelExtension();

        try {
            $configuration = $loader->configLoad(array(), $configuration);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e, '->configLoad() throws an \InvalidArgumentException if the Propel path is not set.');
        }

        $configuration = $loader->configLoad(array('path' => '/propel'), $configuration);
        $this->assertEquals('/propel', $configuration->getParameter('propel.path'), '->configLoad() sets the Propel path');

        $configuration = $loader->configLoad(array(), $configuration);
        $this->assertEquals('/propel', $configuration->getParameter('propel.path'), '->configLoad() sets the Propel path');
    }

    public function testDbalLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new PropelExtension();

        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('Propel', $configuration->getParameter('propel.class'), '->dbalLoad() loads the propel.xml file if not already loaded');

        // propel.dbal.default_connection
        $this->assertEquals('default', $configuration->getParameter('propel.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array('default_connection' => 'foo'), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('propel.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('propel.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');

        $configuration = new BuilderConfiguration();
        $loader = new PropelExtension();
        $configuration = $loader->dbalLoad(array('password' => 'foo'), $configuration);

        $arguments = $configuration->getDefinition('propel.configuration')->getArguments();
        $config = $arguments[0];
        $this->assertEquals('foo', $config['datasources']['default']['connection']['password']);
        $this->assertEquals('root', $config['datasources']['default']['connection']['user']);

        $configuration = $loader->dbalLoad(array('user' => 'foo'), $configuration);
        $this->assertEquals('foo', $config['datasources']['default']['connection']['password']);
        $this->assertEquals('root', $config['datasources']['default']['connection']['user']);
    }
}
