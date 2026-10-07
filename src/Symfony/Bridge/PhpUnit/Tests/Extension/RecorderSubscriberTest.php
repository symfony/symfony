<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Extension\RecorderSubscriber;
use Symfony\Component\HttpClient\Recorder\RecorderMode;

class RecorderSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(RecorderMode::class)) {
            $this->markTestSkipped('symfony/http-client >= 8.2 is required.');
        }
    }

    public static function provideResolveRecordPathCases(): array
    {
        return [
            [null, null, null, '/tests/Foo/FooTest/testBar.har'],
            ['', null, null, '/tests/Foo/FooTest/testBar.har'],
            [null, 3, null, '/tests/Foo/FooTest/testBar@3.har'],
            [null, 'with spaces/and "quotes"', null, '/tests/Foo/FooTest/testBar@with_spaces_and_quotes_.har'],
            ['my.har', 'ignored', null, '/tests/Foo/my.har'],
            ['../shared/my.har', null, null, '/tests/Foo/../shared/my.har'],
            ['/abs/my.har', null, null, '/abs/my.har'],
            ['C:\\records\\my.har', null, null, 'C:\\records\\my.har'],
            ['C:/records/my.har', null, null, 'C:/records/my.har'],
            ['\\\\server\\share\\my.har', null, null, '\\\\server\\share\\my.har'],
            [null, null, '/records', '/records/App/Tests/Foo/FooTest/testBar.har'],
            [null, 'first', '/records', '/records/App/Tests/Foo/FooTest/testBar@first.har'],
            ['my.har', null, '/records', '/records/my.har'],
            ['/abs/my.har', null, '/records', '/abs/my.har'],
        ];
    }

    #[DataProvider('provideResolveRecordPathCases')]
    public function testResolveRecordPath(?string $record, int|string|null $dataSetName, ?string $directory, string $expected)
    {
        $this->assertSame($expected, RecorderSubscriber::resolveRecordPath($record, '/tests/Foo', 'App\\Tests\\Foo\\FooTest', 'testBar', $dataSetName, $directory));
    }

    public static function provideIsAbsolutePathCases(): array
    {
        return [
            ['', false],
            ['my.har', false],
            ['./my.har', false],
            ['/abs/my.har', true],
            ['\\\\server\\share\\my.har', true],
            ['C:\\records\\my.har', true],
            ['C:/records/my.har', true],
        ];
    }

    #[DataProvider('provideIsAbsolutePathCases')]
    public function testIsAbsolutePath(string $path, bool $expected)
    {
        $this->assertSame($expected, RecorderSubscriber::isAbsolutePath($path));
    }
}
