<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use Symfony\Bundle\FrameworkBundle\Kernel\KernelHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class KernelHelperTraitTest extends TestCase
{
    public function testForward()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $kernel = $this->getMock(HttpKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        }));

        $helper = new DummyKernelHelper($kernel, $requestStack);

        $response = $helper->forward('a_controller');
        $this->assertEquals('xml--fr', $response->getContent());
    }

    public function testForwardWithContainer()
    {
        $request = Request::create('/');
        $request->setLocale('fr');
        $request->setRequestFormat('xml');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $kernel = $this->getMock(HttpKernelInterface::class);
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat().'--'.$request->getLocale());
        }));

        $container = new Container();
        $container->set('request_stack', $requestStack);
        $container->set('http_kernel', $kernel);

        $helper = new DummyKernelHelperWithContainer();
        $helper->setContainer($container);

        $response = $helper->forward('a_controller');
        $this->assertEquals('xml--fr', $response->getContent());
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testForwardWithMissingDependencies()
    {
        $helper = new DummyKernelHelperWithContainer();
        $helper->forward('a_controller');
    }
}

class DummyKernelHelper
{
    use KernelHelperTrait {
        forward as public;
    }

    public function __construct(HttpKernelInterface $httpKernel, RequestStack $requestStack)
    {
        $this->httpKernel = $httpKernel;
        $this->requestStack = $requestStack;
    }
}

class DummyKernelHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use KernelHelperTrait {
        forward as public;
    }
}
