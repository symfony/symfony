<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;

class HandledStampTest extends TestCase
{
    public function testConstruct()
    {
        $stamp = new HandledStamp('some result', 'FooHandler::__invoke()', 'foo');

        $this->assertSame('some result', $stamp->getResult());
        $this->assertSame('FooHandler::__invoke()', $stamp->getCallableName());
        $this->assertSame('foo', $stamp->getHandlerAlias());

        $stamp = new HandledStamp('some result', 'FooHandler::__invoke()');

        $this->assertSame('some result', $stamp->getResult());
        $this->assertSame('FooHandler::__invoke()', $stamp->getCallableName());
        $this->assertNull($stamp->getHandlerAlias());
    }

    /**
     * @dataProvider provideCallables
     */
    public function testFromCallable(callable $handler, ?string $expectedHandlerString)
    {
        /** @var HandledStamp $stamp */
        $stamp = HandledStamp::fromCallable($handler, 'some_result', 'alias');
        $this->assertStringMatchesFormat($expectedHandlerString, $stamp->getCallableName());
        $this->assertSame('alias', $stamp->getHandlerAlias(), 'alias is forwarded to construct');
        $this->assertSame('some_result', $stamp->getResult(), 'result is forwarded to construct');
    }

    public function provideCallables()
    {
        yield array(function () {}, 'Closure');
        yield array('var_dump', 'var_dump');
        yield array(new DummyCommandHandler(), DummyCommandHandler::class.'::__invoke');
        yield array(
            array(new DummyCommandHandlerWithSpecificMethod(), 'handle'),
            DummyCommandHandlerWithSpecificMethod::class.'::handle',
        );
        yield array(\Closure::fromCallable(function () {}), 'Closure');
        yield array(\Closure::fromCallable(new DummyCommandHandler()), DummyCommandHandler::class.'::__invoke');
        yield array(\Closure::bind(\Closure::fromCallable(function () {}), new \stdClass()), 'Closure');
        yield array(new class() {
            public function __invoke()
            {
            }
        }, 'class@anonymous%sHandledStampTest.php%s::__invoke');
    }
}

class DummyCommandHandlerWithSpecificMethod
{
    public function handle(): void
    {
    }
}
