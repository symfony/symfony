<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\ResponseListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ResponseListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterDoesNothingForSubRequests()
    {
        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::SUB_REQUEST));
        $response = new Response('foo');

        $this->assertEquals(array(), $this->getResponseListener()->filter($event, $response)->headers->all());
    }

    public function testFilterDoesNothingIfContentTypeIsSet()
    {
        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::MASTER_REQUEST));
        $response = new Response('foo');
        $response->headers->set('Content-Type', 'text/plain');

        $this->assertEquals(array('content-type' => array('text/plain')), $this->getResponseListener()->filter($event, $response)->headers->all());
    }

    public function testFilterDoesNothingIfRequestFormatIsNotDefined()
    {
        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::MASTER_REQUEST, 'request' => Request::create('/')));
        $response = new Response('foo');

        $this->assertEquals(array(), $this->getResponseListener()->filter($event, $response)->headers->all());
    }

    public function testFilterSetContentType()
    {
        $request = Request::create('/');
        $request->setRequestFormat('json');
        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::MASTER_REQUEST, 'request' => $request));
        $response = new Response('foo');

        $this->assertEquals(array('content-type' => array('application/json')), $this->getResponseListener()->filter($event, $response)->headers->all());
    }

    protected function getResponseListener()
    {
        $dispatcher = new EventDispatcher();
        $listener = new ResponseListener();
        $listener->register($dispatcher);

        return $listener;
    }
}
