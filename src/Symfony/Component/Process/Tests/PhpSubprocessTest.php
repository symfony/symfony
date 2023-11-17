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
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class PhpSubprocessTest extends TestCase
{
    private static $phpBin;

    public static function setUpBeforeClass(): void
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === \PHP_SAPI ? 'php' : $phpBin->find());
    }

    /**
     * @dataProvider subprocessProvider
     */
    public function testSubprocess(string $processClass, string $memoryLimit, string $expectedMemoryLimit)
    {
        $process = new Process([self::$phpBin,
            '-d',
            'memory_limit='.$memoryLimit,
            __DIR__.'/OutputMemoryLimitProcess.php',
            '-e', self::$phpBin,
            '-p', $processClass,
        ]);

        $process->mustRun();
        $this->assertEquals($expectedMemoryLimit, trim($process->getOutput()));
    }

    public static function subprocessProvider(): \Generator
    {
        yield 'Process does ignore dynamic memory_limit' => [
            'Process',
            self::getRandomMemoryLimit(),
            self::getDefaultMemoryLimit(),
        ];

        yield 'PhpSubprocess does not ignore dynamic memory_limit' => [
            'PhpSubprocess',
            self::getRandomMemoryLimit(),
            self::getRandomMemoryLimit(),
        ];
    }

    private static function getDefaultMemoryLimit(): string
    {
        return trim(ini_get_all()['memory_limit']['global_value']);
    }

    private static function getRandomMemoryLimit(): string
    {
        $memoryLimit = 123; // Take something that's really unlikely to be configured on a user system.

        while (($formatted = $memoryLimit.'M') === self::getDefaultMemoryLimit()) {
            ++$memoryLimit;
        }

        return $formatted;
    }
}
