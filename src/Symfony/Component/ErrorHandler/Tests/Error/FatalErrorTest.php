<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\Error;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Error\FatalError;

class FatalErrorTest extends TestCase
{
    public function testGetTraceWithoutTraceArgs()
    {
        $originalExceptionWithTrace = (static fn () => new \Exception('Whoops!'))();
        $originalExceptionTrace = array_map(static function ($frame) {
            $frame['args'] ??= ['foo'];

            return $frame;
        }, $originalExceptionWithTrace->getTrace());
        $fatalException = new FatalError('Whoops!', 10, [
            'type' => \E_ERROR,
            'message' => 'Whoops!',
            'file' => '/path/to/file.php',
            'line' => 10,
        ], null, false, $originalExceptionTrace);

        $expectedTrace = array_reduce($originalExceptionTrace, function ($carry, $frame) {
            $this->assertArrayHasKey('args', $frame);

            unset($frame['args']);
            $carry[] = $frame;

            return $carry;
        }, []);

        $this->assertGreaterThan(0, \count($expectedTrace));
        $this->assertSame($expectedTrace, $fatalException->getTrace());
    }

    public function testGetTraceAsStringDoesNotTriggerWarning()
    {
        $this->expectNotToPerformAssertions();

        $originalExceptionWithTrace = (static fn () => new \Exception('Whoops!'))();
        $originalExceptionTrace = array_map(static function ($frame) {
            $frame['args'] ??= ['foo'];

            return $frame;
        }, $originalExceptionWithTrace->getTrace());
        $fatalException = new FatalError('Whoops!', 10, [
            'type' => \E_ERROR,
            'message' => 'Whoops!',
            'file' => '/path/to/file.php',
            'line' => 10,
        ], null, false, $originalExceptionTrace);
        set_error_handler(fn (int $errno, string $errstr) => $this->fail('Error handler should not be called. Received error: '.$errstr));

        try {
            $fatalException->getTraceAsString();
        } finally {
            restore_error_handler();
        }
    }

    public function testGetErrorDoesNotKeepTheTrace()
    {
        $trace = [
            ['file' => '/path/to/caller.php', 'line' => 20, 'function' => 'foo', 'args' => ['secret']],
        ];
        $fatalError = new FatalError('Whoops!', 0, [
            'type' => \E_ERROR,
            'message' => 'Whoops!',
            'file' => '/path/to/file.php',
            'line' => 10,
            'trace' => $trace,
        ], null, true, $trace);

        $this->assertSame($trace, $fatalError->getTrace());
        $this->assertSame([
            'type' => \E_ERROR,
            'message' => 'Whoops!',
            'file' => '/path/to/file.php',
            'line' => 10,
        ], $fatalError->getError());
    }
}
