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
use Symfony\Component\HttpKernel\Event\FilterResponseEventArgs;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\EventManager;

class EsiListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterDoesNothingForSubRequests()
    {
        $evm = new EventManager();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $response = new Response('foo <esi:include src="" />');
        $listener = new EsiListener(new Esi());

        $evm->addEventListener(Events::filterCoreResponse, $listener);
        $eventArgs = new FilterResponseEventArgs($kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('', $eventArgs->getResponse()->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsSomeEsiIncludes()
    {
        $evm = new EventManager();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $response = new Response('foo <esi:include src="" />');
        $listener = new EsiListener(new Esi());

        $evm->addEventListener(Events::filterCoreResponse, $listener);
        $eventArgs = new FilterResponseEventArgs($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('content="ESI/1.0"', $eventArgs->getResponse()->headers->get('Surrogate-Control'));
    }

    public function testFilterWhenThereIsNoEsiIncludes()
    {
        $evm = new EventManager();
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $response = new Response('foo');
        $listener = new EsiListener(new Esi());

        $evm->addEventListener(Events::filterCoreResponse, $listener);
        $eventArgs = new FilterResponseEventArgs($kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $evm->dispatchEvent(Events::filterCoreResponse, $eventArgs);

        $this->assertEquals('', $eventArgs->getResponse()->headers->get('Surrogate-Control'));
    }
}
