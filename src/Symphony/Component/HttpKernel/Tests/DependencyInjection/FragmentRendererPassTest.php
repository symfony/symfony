<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ServiceLocator;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symphony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class FragmentRendererPassTest extends TestCase
{
    /**
     * Tests that content rendering not implementing FragmentRendererInterface
     * triggers an exception.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testContentRendererWithoutInterface()
    {
        $builder = new ContainerBuilder();
        $fragmentHandlerDefinition = $builder->register('fragment.handler');
        $builder->register('my_content_renderer', 'Symphony\Component\DependencyInjection\Definition')
            ->addTag('kernel.fragment_renderer', array('alias' => 'foo'));

        $pass = new FragmentRendererPass();
        $pass->process($builder);

        $this->assertEquals(array(array('addRendererService', array('foo', 'my_content_renderer'))), $fragmentHandlerDefinition->getMethodCalls());
    }

    public function testValidContentRenderer()
    {
        $builder = new ContainerBuilder();
        $fragmentHandlerDefinition = $builder->register('fragment.handler')
            ->addArgument(null);
        $builder->register('my_content_renderer', 'Symphony\Component\HttpKernel\Tests\DependencyInjection\RendererService')
            ->addTag('kernel.fragment_renderer', array('alias' => 'foo'));

        $pass = new FragmentRendererPass();
        $pass->process($builder);

        $serviceLocatorDefinition = $builder->getDefinition((string) $fragmentHandlerDefinition->getArgument(0));
        $this->assertSame(ServiceLocator::class, $serviceLocatorDefinition->getClass());
        $this->assertEquals(array('foo' => new ServiceClosureArgument(new Reference('my_content_renderer'))), $serviceLocatorDefinition->getArgument(0));
    }
}

class RendererService implements FragmentRendererInterface
{
    public function render($uri, Request $request = null, array $options = array())
    {
    }

    public function getName()
    {
        return 'test';
    }
}
