<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests;

use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;

class TwigEngineTest extends TestCase
{
    public function testEvaluateAddsAppGlobal()
    {
        $environment = $this->getTwigEnvironment();
        $container = $this->getContainer();
        $engine = new TwigEngine($environment, new TemplateNameParser(), $app = new GlobalVariables($container));

        $template = $this->getMock('\Twig_TemplateInterface');

        $environment->expects($this->once())
            ->method('loadTemplate')
            ->will($this->returnValue($template));

        $engine->render('name');

        $request = $container->get('request');
        $globals = $environment->getGlobals();
        $this->assertSame($app, $globals['app']);
    }

    public function testEvaluateWithoutAvailableRequest()
    {
        $environment = $this->getTwigEnvironment();
        $container = new Container();
        $engine = new TwigEngine($environment, new TemplateNameParser(), new GlobalVariables($container));

        $template = $this->getMock('\Twig_TemplateInterface');

        $environment->expects($this->once())
            ->method('loadTemplate')
            ->will($this->returnValue($template));

        $container->set('request', null);

        $engine->render('name');

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
