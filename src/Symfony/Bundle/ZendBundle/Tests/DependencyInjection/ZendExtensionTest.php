<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\ZendBundle\Tests\DependencyInjection;

use Symfony\Bundle\ZendBundle\Tests\TestCase;
use Symfony\Bundle\ZendBundle\DependencyInjection\ZendExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ZendExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        // logger
        $container = new ContainerBuilder();
        $loader = new ZendExtension();

        $loader->configLoad(array('logger' => array()), $container);
        $this->assertEquals('Symfony\\Bundle\\ZendBundle\\Logger\\Logger', $container->getParameter('zend.logger.class'), '->loggerLoad() loads the logger.xml file if not already loaded');

        $loader->configLoad(array('logger' => array('priority' => 3)), $container);
        $this->assertEquals(3, $container->getParameter('zend.logger.priority'), '->loggerLoad() overrides existing configuration options');
    }
}
