<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

class ProcessHelperTest extends TestCase
{
    #[DataProvider('provideCommandsAndOutput')]
    public function testVariousProcessRuns(array $expectedOutputLines, bool $successful, Process|string|array $cmd, int $verbosity, ?string $error)
    {
        if (\is_string($cmd)) {
            $cmd = Process::fromShellCommandline($cmd);
        }

        $helper = new ProcessHelper();
        $helper->setHelperSet(new HelperSet([new DebugFormatterHelper()]));
        $outputStream = $this->getOutputStream($verbosity);
        $helper->run($outputStream, $cmd, $error);

        $expectedLines = 1 + \count($expectedOutputLines);

        if (StreamOutput::VERBOSITY_VERY_VERBOSE <= $verbosity) {
            // the executed command and the result are displayed
            $expectedLines += 2;
        }

        if (null !== $error) {
            ++$expectedLines;
        }

        $output = explode("\n", $this->getOutput($outputStream));

        $this->assertCount($expectedLines, $output);

        // remove the trailing newline
        array_pop($output);

        if (null !== $error) {
            $this->assertSame($error, array_pop($output));
        }

        if (StreamOutput::VERBOSITY_VERY_VERBOSE <= $verbosity) {
            if ($cmd instanceof Process) {
                $expectedCommandLine = $cmd->getCommandLine();
            } elseif (\is_array($cmd) && $cmd[0] instanceof Process) {
                $expectedCommandLine = $cmd[0]->getCommandLine();
            } elseif (\is_array($cmd)) {
                $expectedCommandLine = (new Process($cmd))->getCommandLine();
            } else {
                $expectedCommandLine = $cmd;
            }

            $this->assertSame('  RUN  '.$expectedCommandLine, array_shift($output));

            if ($successful) {
                $this->assertSame('  RES  Command ran successfully', array_pop($output));
            } else {
                $this->assertSame('  RES  252 Command did not run successfully', array_pop($output));
            }
        }

        if ([] !== $expectedOutputLines) {
            sort($expectedOutputLines);
            sort($output);

            $this->assertEquals($expectedOutputLines, $output);
        }
    }

    public function testPassedCallbackIsExecuted()
    {
        $helper = new ProcessHelper();
        $helper->setHelperSet(new HelperSet([new DebugFormatterHelper()]));
        $output = $this->getOutputStream(StreamOutput::VERBOSITY_NORMAL);

        $executed = false;
        $callback = function () use (&$executed) { $executed = true; };

        $helper->run($output, ['php', '-r', 'echo 42;'], null, $callback);
        $this->assertTrue($executed);
    }

    public static function provideCommandsAndOutput(): array
    {
        $PHP = '\\' === \DIRECTORY_SEPARATOR ? '"!PHP!"' : '"$PHP"';

        return [
            [[], true, 'php -r "echo 42;"', StreamOutput::VERBOSITY_VERBOSE, null],
            [[], true, 'php -r "echo 42;"', StreamOutput::VERBOSITY_VERY_VERBOSE, null],
            [['  OUT  42'], true, 'php -r "echo 42;"', StreamOutput::VERBOSITY_DEBUG, null],
            [['  OUT  <info>42</info>'], true, 'php -r "echo \'<info>42</info>\';"', StreamOutput::VERBOSITY_DEBUG, null],
            [[], false, 'php -r "syntax error"', StreamOutput::VERBOSITY_VERBOSE, null],
            [[], false, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERY_VERBOSE, null],
            [['  ERR  error message', '  OUT  out message'], false, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_DEBUG, null],
            [[], false, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERBOSE, 'An error occurred'],
            [[], false, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERY_VERBOSE, 'An error occurred'],
            [['  ERR  error message', '  OUT  out message'], false, 'php -r "fwrite(STDERR, \'error message\');usleep(500000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_DEBUG, 'An error occurred'],
            [['  OUT  42'], true, ['php', '-r', 'echo 42;'], StreamOutput::VERBOSITY_DEBUG, null],
            [['  OUT  42'], true, Process::fromShellCommandline('php -r "echo 42;"'), StreamOutput::VERBOSITY_DEBUG, null],
            [['  OUT  42'], true, [new Process(['php', '-r', 'echo 42;'])], StreamOutput::VERBOSITY_DEBUG, null],
            [['  OUT  42'], true, [Process::fromShellCommandline('php -r '.$PHP), 'PHP' => 'echo 42;'], StreamOutput::VERBOSITY_DEBUG, null],
        ];
    }

    private function getOutputStream($verbosity): StreamOutput
    {
        return new StreamOutput(fopen('php://memory', 'r+', false), $verbosity, false);
    }

    private function getOutput(StreamOutput $output): string
    {
        rewind($output->getStream());

        return str_replace(\PHP_EOL, "\n", stream_get_contents($output->getStream()));
    }
}
