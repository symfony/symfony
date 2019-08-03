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

use Symfony\Bundle\FrameworkBundle\Templating\GlobalVariables;
use Symfony\Bundle\FrameworkBundle\Templating\PhpEngine;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Templating\TemplateNameParser;

class PhpEngineTest extends TestCase
{
    public function testEvaluateAddsAppGlobal()
    {
        $container = $this->getContainer();
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser(), $container, $loader, $app = new GlobalVariables($container));
        $globals = $engine->getGlobals();
        $this->assertSame($app, $globals['app']);
    }

    public function testEvaluateWithoutAvailableRequest()
    {
        $container = new Container();
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser(), $container, $loader, new GlobalVariables($container));

        $this->assertFalse($container->has('request_stack'));
        $globals = $engine->getGlobals();
        $this->assertEmpty($globals['app']->getRequest());
    }

    public function testGetInvalidHelper()
    {
        $this->expectException('InvalidArgumentException');
        $container = $this->getContainer();
        $loader = $this->getMockForAbstractClass('Symfony\Component\Templating\Loader\Loader');
        $engine = new PhpEngine(new TemplateNameParser(), $container, $loader);

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
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $stack = new RequestStack();
        $stack->push($request);

        $request->setSession($session);
        $container->set('request_stack', $stack);

        return $container;
    }
}
