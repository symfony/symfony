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
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\PhpProcess;

class PhpProcessTest extends TestCase
{
    public function testNonBlockingWorks()
    {
        $expected = 'hello world!';
        $process = new PhpProcess(<<<PHP
<?php echo '$expected';
PHP
        );
        $process->start();
        $process->wait();
        self::assertEquals($expected, $process->getOutput());
    }

    public function testCommandLine()
    {
        $process = new PhpProcess(<<<'PHP'
<?php echo phpversion().PHP_SAPI;
PHP
        );

        $commandLine = $process->getCommandLine();

        $process->start();
        self::assertStringContainsString($commandLine, $process->getCommandLine(), '::getCommandLine() returns the command line of PHP after start');

        $process->wait();
        self::assertStringContainsString($commandLine, $process->getCommandLine(), '::getCommandLine() returns the command line of PHP after wait');

        self::assertSame(\PHP_VERSION.\PHP_SAPI, $process->getOutput());
    }

    public function testPassingPhpExplicitly()
    {
        $finder = new PhpExecutableFinder();
        $php = array_merge([$finder->find(false)], $finder->findArguments());

        $expected = 'hello world!';
        $script = <<<PHP
<?php echo '$expected';
PHP;
        $process = new PhpProcess($script, null, null, 60, $php);
        $process->run();
        self::assertEquals($expected, $process->getOutput());
    }

    public function testProcessCannotBeCreatedUsingFromShellCommandLine()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('The "Symfony\Component\Process\PhpProcess::fromShellCommandline()" method cannot be called when using "Symfony\Component\Process\PhpProcess".');
        PhpProcess::fromShellCommandline(<<<PHP
<?php echo 'Hello World!';
PHP
        );
    }
}
