<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Debug\WrappedListener;
use Symfony\Component\EventDispatcher\Debug\WrappingListenerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class WrappedListenerTest extends TestCase
{
    /**
     * @dataProvider getListeners
     */
    public function testGetPretty($listener, $pretty)
    {
        $wrappedListener = new WrappedListener($listener, 'name', $this->createStopwatchMock());

        $this->assertSame($pretty, $wrappedListener->getPretty());
    }

    /**
     * @dataProvider getListeners
     */
    public function testStub($listener, $pretty)
    {
        $wrappedListener = new WrappedListener($listener, 'name', $this->createStopwatchMock());

        $info = $wrappedListener->getInfo('event');
        $this->assertSame($pretty.'()', (string) $info['stub']);
    }

    public function getListeners()
    {
        return array(
            array(array($this, 'getListeners'), __METHOD__),
            array(function () {}, 'closure'),
            array(/** @closure-proxy App\Foo::bar */ function () {}, 'App\Foo::bar'),
            array('strtolower', 'strtolower'),
            array(new Listener(), Listener::class.'::__invoke'),
            array(new DecoratedListener(), 'listener'),
            array(new WrappedListener(new DecoratedListener(), 'name', $this->createStopwatchMock()), 'listener'),
        );
    }

    private function createStopwatchMock()
    {
        return $this->getMockBuilder(Stopwatch::class)->getMock();
    }
}

class Listener
{
    public function __invoke()
    {
    }
}

class DecoratedListener implements WrappingListenerInterface
{
    public function getWrappedListener()
    {
        return 'listener';
    }

    public function __invoke()
    {
    }
}
