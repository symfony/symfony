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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class FrameworkExtensionTest extends TestCase
{
    public function testConfigLoad()
    {
        $container = $this->getContainer();
        $loader = new FrameworkExtension();

        $loader->configLoad(array(array()), $container);
        $this->assertEquals('Symfony\\Bundle\\FrameworkBundle\\RequestListener', $container->getParameter('request_listener.class'), '->webLoad() loads the web.xml file if not already loaded');

        $container = $this->getContainer();
        $loader = new FrameworkExtension();

        // profiler
        $loader->configLoad(array(array('profiler' => true)), $container);
        $this->assertEquals('Symfony\Component\HttpKernel\Profiler\Profiler', $container->getParameter('profiler.class'), '->configLoad() loads the collectors.xml file if not already loaded');

        // templating
        $loader->configLoad(array(array('templating' => array('engines' => array('php')))), $container);
        $this->assertEquals('Symfony\\Bundle\\FrameworkBundle\\Templating\\PhpEngine', $container->getParameter('templating.engine.php.class'), '->templatingLoad() loads the templating.xml file if not already loaded');

        // validation
        $loader->configLoad(array(array('validation' => array('enabled' => true))), $container);
        $this->assertEquals('Symfony\Component\Validator\Validator', $container->getParameter('validator.class'), '->validationLoad() loads the validation.xml file if not already loaded');
        $this->assertFalse($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() doesn\'t load the annotations service unless its needed');

        $loader->configLoad(array(array('validation' => array('enabled' => true, 'annotations' => true))), $container);
        $this->assertTrue($container->hasDefinition('validator.mapping.loader.annotation_loader'), '->validationLoad() loads the annotations service');
    }

    protected function getContainer()
    {
        return new ContainerBuilder(new ParameterBag(array(
            'kernel.bundles'          => array('FrameworkBundle' => 'Symfony\\Bundle\\FrameworkBundle\\FrameworkBundle'),
            'kernel.root_dir'         => __DIR__,
            'kernel.debug'            => false,
            'kernel.compiled_classes' => array(),
        )));
    }
}
