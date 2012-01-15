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

use Symfony\Bundle\FrameworkBundle\Templating\PhpEngine;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\SessionStorage\ArraySessionStorage;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

class PhpEngineTest extends TestCase
{
    public function testEvaluateAddsAppGlobal()
    {
        $container = $this->getContainer();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser($kernel), $container, $loader, $app = new GlobalVariables($container));
        $globals = $engine->getGlobals();
        $this->assertSame($app, $globals['app']);
    }

    public function testEvaluateWithoutAvailableRequest()
    {
        $container = new Container();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser($kernel), $container, $loader, new GlobalVariables($container));

        $container->set('request', null);

        $globals = $engine->getGlobals();
        $this->assertEmpty($globals['app']->getRequest());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetInvalidHelper()
    {
        $container = $this->getContainer();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser($kernel), $container, $loader);

        $engine->get('non-existing-helper');
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
}
