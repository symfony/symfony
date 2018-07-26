<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\FragmentRendererPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group legacy
 */
class LegacyFragmentRendererPassTest extends TestCase
{
    /**
     * Tests that content rendering not implementing FragmentRendererInterface
     * trigger an exception.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testContentRendererWithoutInterface()
    {
        $builder = new ContainerBuilder();
        $builder->register('fragment.handler');
        $builder->register('my_content_renderer', 'stdClass')
            ->addTag('kernel.fragment_renderer');

        $pass = new FragmentRendererPass();
        $pass->process($builder);
    }

    public function testValidContentRenderer()
    {
        $builder = new ContainerBuilder();
        $fragmentHandlerDefinition = $builder->register('fragment.handler');
        $builder->register('my_content_renderer', 'Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler\RendererService')
            ->addTag('kernel.fragment_renderer');

        $pass = new FragmentRendererPass();
        $pass->process($builder);

        $this->assertEquals(array(array('addRenderer', array(new Reference('my_content_renderer')))), $fragmentHandlerDefinition->getMethodCalls());
    }
}

class RendererService implements \Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface
{
    public function render($uri, Request $request = null, array $options = array())
    {
    }

    public function getName()
    {
        return 'test';
    }
}
