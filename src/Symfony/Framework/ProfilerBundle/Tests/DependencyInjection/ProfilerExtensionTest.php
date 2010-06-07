<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\ProfilerBundle\Tests\DependencyInjection;

use Symfony\Framework\ProfilerBundle\Tests\TestCase;
use Symfony\Framework\ProfilerBundle\DependencyInjection\ProfilerExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class ProfilerExtensionTest extends TestCase
{
    public function testLoggerLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new ProfilerExtension();

        $configuration = $loader->configLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\ProfilerBundle\\DataCollector\\DataCollectorManager', $configuration->getParameter('data_collector_manager.class'), '->configLoad() loads the collectors.xml file if not already loaded');
        $this->assertFalse($configuration->hasParameter('debug.toolbar.class'), '->configLoad() does not load the toolbar.xml file');

        $configuration = $loader->configLoad(array('toolbar' => true), $configuration);
        $this->assertEquals('Symfony\\Framework\\ProfilerBundle\\Listener\\WebDebugToolbar', $configuration->getParameter('debug.toolbar.class'), '->configLoad() loads the collectors.xml file if the toolbar option is given');
    }
}
