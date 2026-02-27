<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Tests\Fixtures\DummyCommandHandler;

class HandlerDescriptorTest extends TestCase
{
    #[DataProvider('provideHandlers')]
    public function testDescriptorNames(callable $handler, ?string $expectedHandlerString)
    {
        $descriptor = new HandlerDescriptor($handler);

        $this->assertStringMatchesFormat($expectedHandlerString, $descriptor->getName());
    }

    public static function provideHandlers(): iterable
    {
        yield [static function () {}, 'Closure'];
        yield ['var_dump', 'var_dump'];
        yield [new DummyCommandHandler(), DummyCommandHandler::class.'::__invoke'];
        yield [
            [new DummyCommandHandlerWithSpecificMethod(), 'handle'],
            DummyCommandHandlerWithSpecificMethod::class.'::handle',
        ];
        yield [\Closure::fromCallable(static function () {}), 'Closure'];
        yield [\Closure::fromCallable(new DummyCommandHandler()), DummyCommandHandler::class.'::__invoke'];
        yield [\Closure::bind(\Closure::fromCallable(function () {}), new \stdClass()), 'Closure'];
        yield [new class {
            public function __invoke()
            {
            }
        }, 'class@anonymous%sHandlerDescriptorTest.php%s::__invoke'];
    }

    public function testGetOptions()
    {
        $options = ['option1' => 'value1', 'option2' => 'value2'];
        $descriptor = new HandlerDescriptor(static function () {}, $options);

        $this->assertSame($options, $descriptor->getOptions());
    }
}

class DummyCommandHandlerWithSpecificMethod
{
    public function handle(): void
    {
    }
}

// @php-cs-fixer-ignore static_lambda Lambda explicitly binded to an object, cannot be static
