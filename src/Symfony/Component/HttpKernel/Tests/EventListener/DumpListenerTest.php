<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\DumpListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\VarDumper;

/**
 * DumpListenerTest
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testSubscribedEvents()
    {
        $this->assertSame(
            array(KernelEvents::REQUEST => array('configure', 1024)),
            DumpListener::getSubscribedEvents()
        );
    }

    public function testConfigure()
    {
        $prevDumper = VarDumper::setHandler('var_dump');
        VarDumper::setHandler($prevDumper);

        $cloner = new MockCloner();
        $dumper = new MockDumper();

        ob_start();
        $exception = null;
        $listener = new DumpListener($cloner, $dumper);

        $response = new Response();
        $event = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($response))
        ;

        try {
            $listener->configure($event);

            VarDumper::dump('foo');
            VarDumper::dump('bar');

            $this->assertSame('+foo-+bar-', ob_get_clean());
        } catch (\Exception $exception) {
        }

        VarDumper::setHandler($prevDumper);

        if (null !== $exception) {
            throw $exception;
        }
    }
}

class MockCloner implements ClonerInterface
{
    public function cloneVar($var)
    {
        return new Data(array($var.'-'));
    }
}

class MockDumper implements DataDumperInterface
{
    public function dump(Data $data)
    {
        $rawData = $data->getRawData();

        echo '+'.$rawData[0];
    }
}
