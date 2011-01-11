<?php

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\FrameworkBundle\Templating\Engine;
use Symfony\Bundle\TwigBundle\Renderer\Renderer;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Bundle\TwigBundle\GlobalVariables;

class RendererTest extends TestCase
{
    public function testEvalutateAddsAppGlobal()
    {
        $environment = $this->getTwigEnvironment();
        $container = $this->getContainer();
        $renderer = new Renderer($environment, $app = new GlobalVariables($container));

        $engine = new Engine($container, $this->getMock('Symfony\Component\Templating\Loader\LoaderInterface'), array());

        $storage = $this->getStorage();
        $template = $this->getMock('\Twig_TemplateInterface');

        $environment->expects($this->once())
            ->method('loadTemplate')
            ->with($storage)
            ->will($this->returnValue($template));

        $renderer->setEngine($engine);
        $renderer->evaluate($storage);

        $request = $container->get('request');
        $globals = $environment->getGlobals();
        $this->assertSame($app, $globals['app']);
    }

    public function testEvalutateWithoutAvailableRequest()
    {
        $environment = $this->getTwigEnvironment();
        $container = new Container();
        $renderer = new Renderer($environment, new GlobalVariables($container));

        $engine = new Engine($container, $this->getMock('Symfony\Component\Templating\Loader\LoaderInterface'), array());

        $storage = $this->getStorage();
        $template = $this->getMock('\Twig_TemplateInterface');

        $environment->expects($this->once())
            ->method('loadTemplate')
            ->with($storage)
            ->will($this->returnValue($template));

        $container->set('request', null);
        $renderer->setEngine($engine);
        $renderer->evaluate($storage);

        $globals = $environment->getGlobals();
        $this->assertEmpty($globals['app']->getRequest());
    }

    /**
     * Creates a Container with a Session-containing Request service.
     *
     * @return Container
     */
    protected function getContainer()
    {
        $container = new Container();
        $request = new Request();
        $session = new Session(new ArraySessionStorage());

        $request->setSession($session);
        $container->set('request', $request);

        return $container;
    }

    /**
     * Creates a mock Storage object.
     *
     * @return Storage
     */
    protected function getStorage()
    {
        return $this
            ->getMockBuilder('Symfony\Component\Templating\Storage\Storage')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Creates a mock Twig_Environment object.
     *
     * @return \Twig_Environment
     */
    protected function getTwigEnvironment()
    {
        return $this
            ->getMockBuilder('\Twig_Environment')
            ->setMethods(array('loadTemplate'))
            ->getMock();
    }
}