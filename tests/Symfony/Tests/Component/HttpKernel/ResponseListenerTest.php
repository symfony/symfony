<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\ResponseListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEventArgs;
use Symfony\Component\HttpKernel\Events;
use Doctrine\Common\EventManager;

class ResponseListenerTest extends \PHPUnit_Framework_TestCase
{
    private $evm;

    private $kernel;

    protected function setUp()
    {
        $this->evm = new EventManager();
        $listener = new ResponseListener('UTF-8');
        $this->evm->addEventListener(Events::filterCoreResponse, $listener);

        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

    }
    public function testFilterDoesNothingForSubRequests()
    {
        $response = new Response('foo');

        $eventArgs = new FilterResponseEventArgs($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $this->evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('', $eventArgs->getResponse()->headers->get('content-type'));
    }

    public function testFilterDoesNothingIfContentTypeIsSet()
    {
        $response = new Response('foo');
        $response->headers->set('Content-Type', 'text/plain');

        $eventArgs = new FilterResponseEventArgs($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('text/plain', $eventArgs->getResponse()->headers->get('content-type'));
    }

    public function testFilterDoesNothingIfRequestFormatIsNotDefined()
    {
        $response = new Response('foo');

        $eventArgs = new FilterResponseEventArgs($this->kernel, Request::create('/'), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('', $eventArgs->getResponse()->headers->get('content-type'));
    }

    public function testFilterSetContentType()
    {
        $response = new Response('foo');
        $request = Request::create('/');
        $request->setRequestFormat('json');

        $eventArgs = new FilterResponseEventArgs($this->kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);
        $this->evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('application/json', $eventArgs->getResponse()->headers->get('content-type'));
    }
}
