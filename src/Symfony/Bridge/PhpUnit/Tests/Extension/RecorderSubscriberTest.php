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

    public static function provideResolveRecordPathCases()
    {
        return [
            [null, '/tests/Foo', 'FooTest', 'testBar', '/records/', '/tests/Foo/FooTest/testBar.har'],
            ['', '/tests/Foo', 'FooTest', 'testBar', '/records/', '/tests/Foo/FooTest/testBar.har'],
            ['my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', '/tests/Foo/my.har'],
            ['../shared/my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', '/tests/Foo/../shared/my.har'],
            ['@shared/my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', '/records/shared/my.har'],
            ['/abs/my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', '/abs/my.har'],
            ['C:\\records\\my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', 'C:\\records\\my.har'],
            ['C:/records/my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', 'C:/records/my.har'],
            ['\\\\server\\share\\my.har', '/tests/Foo', 'FooTest', 'testBar', '/records/', '\\\\server\\share\\my.har'],
        ];
    }

    #[DataProvider('provideResolveRecordPathCases')]
    public function testResolveRecordPath($record, $testDir, $shortClassName, $methodName, $defaultDirectory, $expected)
    {
        $result = RecorderSubscriber::resolveRecordPath($record, $testDir, $shortClassName, $methodName, $defaultDirectory);
        $this->assertSame($expected, $result);
    }

    public static function provideIsAbsolutePathCases()
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
    public function testIsAbsolutePath($path, $expected)
    {
        $this->assertSame($expected, RecorderSubscriber::isAbsolutePath($path));
    }
}
