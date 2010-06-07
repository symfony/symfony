<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\DoctrineBundle\Tests\DependencyInjection;

use Symfony\Framework\DoctrineBundle\Tests\TestCase;
use Symfony\Framework\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class DoctrineExtensionTest extends TestCase
{
    public function testDbalLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());

        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\DoctrineBundle\\DataCollector\\DoctrineDataCollector', $configuration->getParameter('doctrine.data_collector.class'), '->dbalLoad() loads the dbal.xml file if not already loaded');

        // doctrine.dbal.default_connection
        $this->assertEquals('default', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array('default_connection' => 'foo'), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');
        $configuration = $loader->dbalLoad(array(), $configuration);
        $this->assertEquals('foo', $configuration->getParameter('doctrine.dbal.default_connection'), '->dbalLoad() overrides existing configuration options');

        $configuration = new BuilderConfiguration();
        $loader = new DoctrineExtension(array(), array());
        $configuration = $loader->dbalLoad(array('password' => 'foo'), $configuration);

        $arguments = $configuration->getDefinition('doctrine.dbal.default_connection')->getArguments();
        $config = $arguments[0];

        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);

        $configuration = $loader->dbalLoad(array('user' => 'foo'), $configuration);
        $this->assertEquals('foo', $config['password']);
        $this->assertEquals('root', $config['user']);
    }
}
