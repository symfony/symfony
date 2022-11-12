<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Pusher\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Pusher\PusherOptions;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 *
 * @internal
 * @coversNothing
 */
final class PusherOptionsTest extends TestCase
{
    /**
     * @dataProvider toArrayProvider
     * @dataProvider toArraySimpleOptionsProvider
     */
    public function testToArray(array $options, array $expected = null): void
    {
        static::assertSame($expected ?? $options, (new PusherOptions($options))->toArray());
    }

    public function toArrayProvider(): iterable
    {
        yield 'empty is allowed' => [
            [],
            [],
        ];
    }

    public function toArraySimpleOptionsProvider(): iterable
    {
        yield [[]];
    }

    public function setProvider(): iterable
    {
        yield ['async', 'async', true];
    }
}
