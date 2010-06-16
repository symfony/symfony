<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\WebBundle\Tests\DependencyInjection;

use Symfony\Framework\WebBundle\Tests\TestCase;
use Symfony\Framework\WebBundle\DependencyInjection\WebExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class WebExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new WebExtension();

        $configuration = $loader->configLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\WebBundle\\Listener\\RequestParser', $configuration->getParameter('request_parser.class'), '->webLoad() loads the web.xml file if not already loaded');

        $configuration = new BuilderConfiguration();
        $loader = new WebExtension();

        $configuration = $loader->configLoad(array('profiler' => true), $configuration);
        $this->assertEquals('Symfony\\Framework\\WebBundle\\Profiler', $configuration->getParameter('profiler.class'), '->configLoad() loads the collectors.xml file if not already loaded');
        $this->assertFalse($configuration->hasParameter('debug.toolbar.class'), '->configLoad() does not load the toolbar.xml file');

        $configuration = $loader->configLoad(array('toolbar' => true), $configuration);
        $this->assertEquals('Symfony\\Components\\HttpKernel\\Listener\\WebDebugToolbar', $configuration->getParameter('debug.toolbar.class'), '->configLoad() loads the collectors.xml file if the toolbar option is given');
    }

    public function testUserLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new WebExtension();

        $configuration = $loader->userLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\WebBundle\\User', $configuration->getParameter('user.class'), '->userLoad() loads the user.xml file if not already loaded');
    }

    public function testTemplatingLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = new WebExtension();

        $configuration = $loader->templatingLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\WebBundle\\Templating\\Engine', $configuration->getParameter('templating.engine.class'), '->templatingLoad() loads the templating.xml file if not already loaded');
    }
}
