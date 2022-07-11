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
use Symfony\Component\EventDispatcher\Debug\AbstractNamedListener;
use Symfony\Component\EventDispatcher\Debug\NamedListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NamedListenerTest extends TestCase
{
    /**
     * @dataProvider provideListenersToDescribe
     */
    public function testListenerDescription($listener, $expected)
    {
        $wrappedListener = new WrappingNamedListener($listener, null);

        $this->assertSame($expected, [
            $wrappedListener->getName(),
            $wrappedListener->getPretty(),
            $wrappedListener->getCallableRef(),
        ]);
    }

    /**
     * @dataProvider provideListenersToDescribe
     */
    public function testListenerDescriptionWithNameOverride($listener, $expected)
    {
        $wrappedListener = new WrappingNamedListener($listener, 'name_override');

        $this->assertSame(['name_override', $expected[1], $expected[2]], [
            $wrappedListener->getName(),
            $wrappedListener->getPretty(),
            $wrappedListener->getCallableRef(),
        ]);
    }

    public function provideListenersToDescribe(): array
    {
        return [
            [new BarListener(), ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::__invoke', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::__invoke']],
            [new WrappingNamedListener(new BarListener(), null), ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::__invoke', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::__invoke']],
            [[new BarListener(), 'listen'], ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listen', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listen']],
            [['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'listenStatic'],  ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listenStatic', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listenStatic']],
            [['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'invalidMethod'], ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::invalidMethod', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::invalidMethod']],
            ['var_dump', ['var_dump', 'var_dump', null]],
            [function () {}, ['closure', 'closure', null]],
            [\Closure::fromCallable([new BarListener(), 'listen']), ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listen', null]],
            [\Closure::fromCallable(['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'listenStatic']), ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listenStatic', null]],
            [\Closure::fromCallable(function () {}), ['closure', 'closure', null]],
            [[#[\Closure(name: BarListener::class)] static fn () => new BarListener(), 'listen'], ['Symfony\Component\EventDispatcher\Tests\Debug\BarListener', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listen', 'Symfony\Component\EventDispatcher\Tests\Debug\BarListener::listen']],
            [new BarNamedListener('name', 'pretty', 'callable_ref'), ['name', 'pretty', 'callable_ref']],
            [[new BarNamedListener('name', 'pretty', 'callable_ref'), 'getPretty'], ['name', 'name::getPretty', 'callable_ref::getPretty']],
        ];
    }
}

class WrappingNamedListener extends AbstractNamedListener
{
    public function __invoke(object $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
    }
}

class BarListener
{
    public function listen()
    {
    }

    public function __invoke()
    {
    }

    public static function listenStatic()
    {
    }
}

class BarNamedListener implements NamedListener
{
    public function __construct(
        private readonly string $name,
        private readonly string $pretty,
        private readonly ?string $callableRef = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPretty(): string
    {
        return $this->pretty;
    }

    public function getCallableRef(): ?string
    {
        return $this->callableRef;
    }

    public function __invoke()
    {
    }
}
