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
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class ZendExtensionTest extends TestCase
{
    public function testLoggerLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new ZendExtension();

        $configuration = $loader->loggerLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Bundle\\ZendBundle\\Logger\\Logger', $configuration->getParameter('zend.logger.class'), '->loggerLoad() loads the logger.xml file if not already loaded');

        $configuration = $loader->loggerLoad(array('priority' => 3), $configuration);
        $this->assertEquals(3, $configuration->getParameter('zend.logger.priority'), '->loggerLoad() overrides existing configuration options');
    }
}
