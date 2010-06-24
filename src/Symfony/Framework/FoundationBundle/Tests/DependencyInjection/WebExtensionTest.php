<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\FoundationBundle\Tests\DependencyInjection;

use Symfony\Framework\FoundationBundle\Tests\TestCase;
use Symfony\Framework\FoundationBundle\DependencyInjection\WebExtension;
use Symfony\Components\DependencyInjection\BuilderConfiguration;

class WebExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = $this->getWebExtension();

        $configuration = $loader->configLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\FoundationBundle\\Listener\\RequestParser', $configuration->getParameter('request_parser.class'), '->webLoad() loads the web.xml file if not already loaded');

        $configuration = new BuilderConfiguration();
        $loader = $this->getWebExtension();

        $configuration = $loader->configLoad(array('profiler' => true), $configuration);
        $this->assertEquals('Symfony\\Framework\\FoundationBundle\\Profiler', $configuration->getParameter('profiler.class'), '->configLoad() loads the collectors.xml file if not already loaded');
        $this->assertFalse($configuration->hasParameter('debug.toolbar.class'), '->configLoad() does not load the toolbar.xml file');

        $configuration = $loader->configLoad(array('toolbar' => true), $configuration);
        $this->assertEquals('Symfony\\Components\\HttpKernel\\Listener\\WebDebugToolbar', $configuration->getParameter('debug.toolbar.class'), '->configLoad() loads the collectors.xml file if the toolbar option is given');
    }

    public function testUserLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = $this->getWebExtension();

        $configuration = $loader->userLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\FoundationBundle\\User', $configuration->getParameter('user.class'), '->userLoad() loads the user.xml file if not already loaded');
    }

    public function testTemplatingLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = $this->getWebExtension();

        $configuration = $loader->templatingLoad(array(), $configuration);
        $this->assertEquals('Symfony\\Framework\\FoundationBundle\\Templating\\Engine', $configuration->getParameter('templating.engine.class'), '->templatingLoad() loads the templating.xml file if not already loaded');
    }

    public function testValidationLoad()
    {
        $configuration = new BuilderConfiguration();
        $loader = $this->getWebExtension();

        $configuration = $loader->configLoad(array('validation' => true), $configuration);
        $this->assertEquals('Symfony\Components\Validator\Validator', $configuration->getParameter('validator.class'), '->validationLoad() loads the validation.xml file if not already loaded');
        $this->assertFalse($configuration->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() doesn\'t load the annotations service unless its needed');

        $configuration = $loader->configLoad(array('validation' => array('annotations' => true)), $configuration);
        $this->assertTrue($configuration->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() loads the annotations service');
    }

    public function getWebExtension() {
        return new WebExtension(array(
            'Symfony\\Framework' => __DIR__ . '/../../../Framework',
        ), array(
            'FoundationBundle',
        ));
    }
}
