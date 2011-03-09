<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\HttpCache;

use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\EsiListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class EsiListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterDoesNothingForSubRequests()
    {
        $dispatcher = new EventDispatcher();
        $listener = new EsiListener(new Esi());
        $dispatcher->connect('core.response', array($listener, 'filter'));

        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::SUB_REQUEST));
        $dispatcher->filter($event, $response = new Response('foo <esi:include src="" />'));

        $this->assertEquals('', $response->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsSomeEsiIncludes()
    {
        $dispatcher = new EventDispatcher();
        $listener = new EsiListener(new Esi());
        $dispatcher->connect('core.response', array($listener, 'filter'));

        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::MASTER_REQUEST));
        $dispatcher->filter($event, $response = new Response('foo <esi:include src="" />'));

        $this->assertEquals('content="ESI/1.0"', $response->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsNoEsiIncludes()
    {
        $dispatcher = new EventDispatcher();
        $listener = new EsiListener(new Esi());
        $dispatcher->connect('core.response', array($listener, 'filter'));

        $event = new Event(null, 'core.response', array('request_type' => HttpKernelInterface::MASTER_REQUEST));
        $dispatcher->filter($event, $response = new Response('foo'));

        $this->assertEquals('', $response->headers->get('Surrogate-Control'));
    }
}
