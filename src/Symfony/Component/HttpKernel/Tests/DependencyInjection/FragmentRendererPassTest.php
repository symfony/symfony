<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class FragmentRendererPassTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group legacy
     */
    public function testLegacyFragmentRedererWithoutAlias()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        // no alias
        $services = array(
            'my_content_renderer' => array(array()),
        );

        $renderer = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $renderer
            ->expects($this->once())
            ->method('addMethodCall')
            ->with('addRenderer', array(new Reference('my_content_renderer')))
        ;

        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $definition->expects($this->atLeastOnce())
            ->method('getClass')
            ->will($this->returnValue('Symfony\Component\HttpKernel\Tests\DependencyInjection\RendererService'));
        $definition
            ->expects($this->once())
            ->method('isPublic')
            ->will($this->returnValue(true))
        ;

        $builder = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('hasDefinition', 'findTaggedServiceIds', 'getDefinition')
        );
        $builder->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));

        // We don't test kernel.fragment_renderer here
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $builder->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->onConsecutiveCalls($renderer, $definition));

        $pass = new FragmentRendererPass();
        $pass->process($builder);
    }

    /**
     * Tests that content rendering not implementing FragmentRendererInterface
     * trigger an exception.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testContentRendererWithoutInterface()
    {
        // one service, not implementing any interface
        $services = array(
            'my_content_renderer' => array(array('alias' => 'foo')),
        );

        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');

        $builder = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('hasDefinition', 'findTaggedServiceIds', 'getDefinition')
        );
        $builder->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));

        // We don't test kernel.fragment_renderer here
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $builder->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->returnValue($definition));

        $pass = new FragmentRendererPass();
        $pass->process($builder);
    }

    public function testValidContentRenderer()
    {
        $services = array(
            'my_content_renderer' => array(array('alias' => 'foo')),
        );

        $renderer = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $renderer
            ->expects($this->once())
            ->method('addMethodCall')
            ->with('addRendererService', array('foo', 'my_content_renderer'))
        ;

        $definition = $this->getMock('Symfony\Component\DependencyInjection\Definition');
        $definition->expects($this->atLeastOnce())
            ->method('getClass')
            ->will($this->returnValue('Symfony\Component\HttpKernel\Tests\DependencyInjection\RendererService'));
        $definition
            ->expects($this->once())
            ->method('isPublic')
            ->will($this->returnValue(true))
        ;

        $builder = $this->getMock(
            'Symfony\Component\DependencyInjection\ContainerBuilder',
            array('hasDefinition', 'findTaggedServiceIds', 'getDefinition')
        );
        $builder->expects($this->any())
            ->method('hasDefinition')
            ->will($this->returnValue(true));

        // We don't test kernel.fragment_renderer here
        $builder->expects($this->atLeastOnce())
            ->method('findTaggedServiceIds')
            ->will($this->returnValue($services));

        $builder->expects($this->atLeastOnce())
            ->method('getDefinition')
            ->will($this->onConsecutiveCalls($renderer, $definition));

        $pass = new FragmentRendererPass();
        $pass->process($builder);
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
