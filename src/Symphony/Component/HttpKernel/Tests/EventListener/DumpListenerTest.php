<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Console\ConsoleEvents;
use Symphony\Component\HttpKernel\EventListener\DumpListener;
use Symphony\Component\VarDumper\Cloner\ClonerInterface;
use Symphony\Component\VarDumper\Cloner\Data;
use Symphony\Component\VarDumper\Dumper\DataDumperInterface;
use Symphony\Component\VarDumper\VarDumper;

/**
 * DumpListenerTest.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpListenerTest extends TestCase
{
    public function testSubscribedEvents()
    {
        $this->assertSame(
            array(ConsoleEvents::COMMAND => array('configure', 1024)),
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

        try {
            $listener->configure();

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
        return new Data(array(array($var.'-')));
    }
}

class MockDumper implements DataDumperInterface
{
    public function dump(Data $data)
    {
        echo '+'.$data->getValue();
    }
}
