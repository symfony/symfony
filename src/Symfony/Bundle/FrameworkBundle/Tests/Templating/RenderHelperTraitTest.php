<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Bundle\FrameworkBundle\Templating\RenderHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenderHelperTraitTest extends TestCase
{
    public function testRenderWithTemplating()
    {
        $response = new Response();

        $templatingMock = $this->getMock(EngineInterface::class);
        $templatingMock->expects($this->once())
            ->method('renderResponse')
            ->with('some_view', array('foo' => 'bar'), $this->identicalTo($response))
            ->will($this->returnValue($response));

        $renderHelper = new DummyRenderHelperWithTemplating($templatingMock);

        $this->assertSame(
            $response,
            $renderHelper->render('some_view', array('foo' => 'bar'), $response)
        );
    }

    public function testRenderWithTemplatingFromContainer()
    {
        $response = new Response();

        $templatingMock = $this->getMock(EngineInterface::class);
        $templatingMock->expects($this->once())
            ->method('renderResponse')
            ->with('some_view', array('foo' => 'bar'), $this->identicalTo($response))
            ->will($this->returnValue($response));

        $container = new Container();
        $container->set('templating', $templatingMock);
        $renderHelper = new DummyRenderHelperWithContainer();
        $renderHelper->setContainer($container);

        $this->assertSame(
            $response,
            $renderHelper->render('some_view', array('foo' => 'bar'), $response)
        );
    }

    public function testRenderWithTwig()
    {
        $response = new Response();

        $twigMock = $this->getMockBuilder(\Twig_Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twigMock->expects($this->once())
            ->method('render')
            ->with('some_view', array('foo' => 'bar'))
            ->will($this->returnValue('some_content'));

        $renderHelper = new DummyRenderHelperWithTwig($twigMock);

        $this->assertSame(
            $response,
            $renderHelper->render('some_view', array('foo' => 'bar'), $response)
        );
        $this->assertEquals(
            'some_content',
            $response->getContent()
        );
    }

    public function testRenderWithTwigFromContainer()
    {
        $response = new Response();

        $twigMock = $this->getMockBuilder(\Twig_Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twigMock->expects($this->once())
            ->method('render')
            ->with('some_view', array('foo' => 'bar'))
            ->will($this->returnValue('some_content'));

        $container = new Container();
        $container->set('twig', $twigMock);
        $renderHelper = new DummyRenderHelperWithContainer();
        $renderHelper->setContainer($container);

        $this->assertSame(
            $response,
            $renderHelper->render('some_view', array('foo' => 'bar'), $response)
        );
        $this->assertEquals(
            'some_content',
            $response->getContent()
        );
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testRenderWithMissingDependencies()
    {
        $helper = new DummyRenderHelperWithContainer();
        $helper->render('some_view');
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testRenderWitEmptyContainer()
    {
        $helper = new DummyRenderHelperWithContainer();
        $helper->setContainer(new Container());
        $helper->render('some_view');
    }

    public function testRenderViewWithTemplating()
    {
        $templatingMock = $this->getMock(EngineInterface::class);
        $templatingMock->expects($this->once())
            ->method('render')
            ->with('some_view', array('foo' => 'bar'))
            ->will($this->returnValue('some_content'));

        $renderHelper = new DummyRenderHelperWithTemplating($templatingMock);

        $this->assertEquals(
            'some_content',
            $renderHelper->renderView('some_view', array('foo' => 'bar'))
        );
    }

    public function testRenderViewWithTwig()
    {
        $twigMock = $this->getMockBuilder(\Twig_Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twigMock->expects($this->once())
            ->method('render')
            ->with('some_view', array('foo' => 'bar'))
            ->will($this->returnValue('some_content'));

        $renderHelper = new DummyRenderHelperWithTwig($twigMock);

        $this->assertEquals(
            'some_content',
            $renderHelper->renderView('some_view', array('foo' => 'bar'))
        );
    }

    public function testStreamWithTemplating()
    {
        $response = new StreamedResponse();

        $templatingMock = $this->getMockBuilder(DelegatingEngine::class)
            ->disableOriginalConstructor()
            ->getMock();
        $templatingMock->expects($this->once())
            ->method('stream')
            ->with('some_view', array('foo' => 'bar'));

        $renderHelper = new DummyRenderHelperWithTemplating($templatingMock);

        $this->assertSame(
            $response,
            $renderHelper->stream('some_view', array('foo' => 'bar'), $response)
        );

        $response->sendContent();
    }

    public function testStreamWithTwig()
    {
        $response = new StreamedResponse();

        $twigMock = $this->getMockBuilder(\Twig_Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twigMock->expects($this->once())
            ->method('display')
            ->with('some_view', array('foo' => 'bar'));

        $renderHelper = new DummyRenderHelperWithTwig($twigMock);

        $this->assertSame(
            $response,
            $renderHelper->stream('some_view', array('foo' => 'bar'), $response)
        );

        $response->sendContent();
    }
}

class DummyRenderHelperWithTemplating
{
    public function __construct(EngineInterface $templating)
    {
        $this->templating = $templating;
    }

    use RenderHelperTrait {
        renderView as public;
        render as public;
        stream as public;
    }
}

class DummyRenderHelperWithTwig
{
    public function __construct(\Twig_Environment $twig)
    {
        $this->twig = $twig;
    }

    use RenderHelperTrait {
        renderView as public;
        render as public;
        stream as public;
    }
}

class DummyRenderHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use RenderHelperTrait {
        renderView as public;
        render as public;
        stream as public;
    }
}
