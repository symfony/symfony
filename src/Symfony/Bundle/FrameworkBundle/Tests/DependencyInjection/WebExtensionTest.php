<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\WebExtension;
use Symfony\Components\DependencyInjection\ContainerBuilder;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBag;

class WebExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $container = $this->getContainer();
        $loader = new WebExtension();

        $loader->configLoad(array(), $container);
        $this->assertEquals('Symfony\\Bundle\\FrameworkBundle\\RequestListener', $container->getParameter('request_listener.class'), '->webLoad() loads the web.xml file if not already loaded');

        $container = $this->getContainer();
        $loader = new WebExtension();

        $loader->configLoad(array('profiler' => true), $container);
        $this->assertEquals('Symfony\\Bundle\\FrameworkBundle\\Profiler', $container->getParameter('profiler.class'), '->configLoad() loads the collectors.xml file if not already loaded');
        $this->assertFalse($container->getParameterBag()->has('debug.toolbar.class'), '->configLoad() does not load the toolbar.xml file');

        $loader->configLoad(array('toolbar' => true), $container);
        $this->assertEquals('Symfony\\Components\\HttpKernel\\Profiler\\WebDebugToolbarListener', $container->getParameter('debug.toolbar.class'), '->configLoad() loads the collectors.xml file if the toolbar option is given');
    }

    public function testTemplatingLoad()
    {
        $container = $this->getContainer();
        $loader = new WebExtension();

        $loader->templatingLoad(array(), $container);
        $this->assertEquals('Symfony\\Bundle\\FrameworkBundle\\Templating\\Engine', $container->getParameter('templating.engine.class'), '->templatingLoad() loads the templating.xml file if not already loaded');
    }

    public function testValidationLoad()
    {
        $container = $this->getContainer();
        $loader = new WebExtension();

        $loader->configLoad(array('validation' => array('enabled' => true)), $container);
        $this->assertEquals('Symfony\Components\Validator\Validator', $container->getParameter('validator.class'), '->validationLoad() loads the validation.xml file if not already loaded');
        $this->assertFalse($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() doesn\'t load the annotations service unless its needed');

        $loader->configLoad(array('validation' => array('enabled' => true, 'annotations' => true)), $container);
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() loads the annotations service');
    }

    protected function getContainer()
    {
        return new ContainerBuilder(new ParameterBag(array(
            'kernel.bundle_dirs'      => array(
                'Symfony\\Framework' => __DIR__ . '/../../../Framework',
            ),
            'kernel.bundles'          => array(
                'FrameworkBundle',
            ),
            'kernel.debug'            => false,
            'kernel.compiled_classes' => array(),
        )));
    }
}
