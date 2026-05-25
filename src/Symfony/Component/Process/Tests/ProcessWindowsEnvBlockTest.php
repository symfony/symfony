<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\InvalidArgumentException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessWindowsEnvBlockTest extends TestCase
{
    private static string $phpBin;

    public static function setUpBeforeClass(): void
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === \PHP_SAPI ? 'php' : $phpBin->find());
    }

    public function testStartThrowsWhenSingleEnvValueExceedsWindowsLimit()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows-only.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment block size (');

        // "KEY=" (4) + 32767 + "\0" (1) + block terminator (1) = 32773 > 32767
        $this->getProcess([self::$phpBin, '--version'], null, ['KEY' => str_repeat('x', 32767)])->start();
    }

    public function testStartThrowsWhenMultipleEntriesCollectivelyExceedWindowsLimit()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows-only.');
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment block size (');

        $env = [];
        for ($i = 0; $i < 10; ++$i) {
            $env['KEY_'.$i] = str_repeat('v', 3277);
        }

        $this->getProcess([self::$phpBin, '--version'], null, $env)->start();
    }

    public function testStartDoesNotThrowForSmallEnv()
    {
        $p = $this->getProcess([self::$phpBin, '--version'], null, ['SMALL' => 'value']);
        $p->start();
        $p->stop(0);

        $this->assertTrue(true, 'start() must not throw for a small env block.');
    }

    public function testStartDoesNotThrowForEmptyEnv()
    {
        $p = $this->getProcess([self::$phpBin, '--version'], null, []);
        $p->start();
        $p->stop(0);

        $this->assertTrue(true, 'start() must not throw for an empty env block.');
    }

    public function testStartDoesNotThrowForNullEnv()
    {
        $p = new Process([self::$phpBin, '--version']);
        $p->start();
        $p->stop(0);

        $this->assertTrue(true, 'start() must not throw when env is null.');
    }

    public function testStartDoesNotThrowWhenEnvBlockIsExactlyAtWindowsLimit()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows-only.');
        }

        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        // "KEY=" (4) + 32761 + "\0" (1) + terminator (1) = 32767 exactly
        $method->invoke(new Process([self::$phpBin, '--version']), ['KEY='.str_repeat('x', 32761)]);

        $this->assertTrue(true, 'start() must not throw when the env block is exactly at the limit.');
    }

    public function testStartDoesNotThrowWhenFalseEnvValuesExceedLimit()
    {
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows-only.');
        }

        // false values are stripped before the size check
        $p = $this->getProcess([self::$phpBin, '--version'], null, ['IGNORED' => false, 'SMALL' => 'value']);
        $p->start();
        $p->stop(0);

        $this->assertTrue(true, 'false env values must not count toward the block size.');
    }

    public function testWindowsEnvBlockValidationThrowsViaReflection()
    {
        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment block size (');

        $method->invoke(new Process([self::$phpBin, '--version']), ['KEY='.str_repeat('x', 32767)]);
    }

    public function testWindowsEnvBlockValidationPassesAtExactLimitViaReflection()
    {
        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        // "KEY=" (4) + 32761 + "\0" (1) + terminator (1) = 32767 exactly
        $method->invoke(new Process([self::$phpBin, '--version']), ['KEY='.str_repeat('x', 32761)]);

        $this->assertTrue(true, 'No exception must be thrown when the block is exactly at the limit.');
    }

    public function testWindowsEnvBlockValidationCountsMultibyteInCodeUnitsViaReflection()
    {
        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        // "é" = 2 UTF-8 bytes but 1 UTF-16 code unit
        // "KEY=" (4) + 32761 code units + "\0" (1) + terminator (1) = 32767 exactly → must not throw
        $method->invoke(new Process([self::$phpBin, '--version']), ['KEY='.str_repeat('é', 32761)]);

        $this->assertTrue(true, 'Multibyte chars must be counted in UTF-16 code units, not bytes.');
    }

    public function testWindowsEnvBlockValidationCountsSupplementaryCharsAsTwoCodeUnitsViaReflection()
    {
        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        // U+1F389 "🎉" = 4 UTF-8 bytes, 1 codepoint, 2 UTF-16 code units (surrogate pair)
        // "KK=" (3) + 16381 × 2 code units (32762) + "\0" (1) + terminator (1) = 32767 exactly → must not throw
        $method->invoke(new Process([self::$phpBin, '--version']), ['KK='.str_repeat('🎉', 16381)]);

        $this->assertTrue(true, 'Supplementary chars must be counted as 2 UTF-16 code units.');
    }

    public function testWindowsEnvBlockValidationThrowsWhenSupplementaryCharsPushOverLimitViaReflection()
    {
        $method = new \ReflectionMethod(Process::class, 'validateWindowsEnvBlockSize');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The environment block size (');

        // "KK=" (3) + 16382 × 2 code units (32764) + "\0" (1) + terminator (1) = 32769 > 32767
        $method->invoke(new Process([self::$phpBin, '--version']), ['KK='.str_repeat('🎉', 16382)]);
    }

    private function getProcess(array $command, ?string $cwd = null, ?array $env = null, mixed $input = null, ?int $timeout = 60): Process
    {
        return new Process($command, $cwd, $env, $input, $timeout);
    }
}
