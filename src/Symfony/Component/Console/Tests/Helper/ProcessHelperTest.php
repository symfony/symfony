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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\DebugFormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\ProcessHelper;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Process\Process;

class ProcessHelperTest extends TestCase
{
    /**
     * @dataProvider provideCommandsAndOutput
     */
    public function testVariousProcessRuns($expected, $cmd, $verbosity, $error)
    {
        $helper = new ProcessHelper();
        $helper->setHelperSet(new HelperSet([new DebugFormatterHelper()]));
        $output = $this->getOutputStream($verbosity);
        $helper->run($output, $cmd, $error);
        $this->assertEquals($expected, $this->getOutput($output));
    }

    public function testPassedCallbackIsExecuted()
    {
        $helper = new ProcessHelper();
        $helper->setHelperSet(new HelperSet([new DebugFormatterHelper()]));
        $output = $this->getOutputStream(StreamOutput::VERBOSITY_NORMAL);

        $executed = false;
        $callback = function () use (&$executed) { $executed = true; };

        $helper->run($output, 'php -r "echo 42;"', null, $callback);
        $this->assertTrue($executed);
    }

    public function provideCommandsAndOutput()
    {
        $successOutputVerbose = <<<'EOT'
  RUN  php -r "echo 42;"
  RES  Command ran successfully

EOT;
        $successOutputDebug = <<<'EOT'
  RUN  php -r "echo 42;"
  OUT  42
  RES  Command ran successfully

EOT;
        $successOutputDebugWithTags = <<<'EOT'
  RUN  php -r "echo '<info>42</info>';"
  OUT  <info>42</info>
  RES  Command ran successfully

EOT;
        $successOutputProcessDebug = <<<'EOT'
  RUN  'php' '-r' 'echo 42;'
  OUT  42
  RES  Command ran successfully

EOT;
        $syntaxErrorOutputVerbose = <<<'EOT'
  RUN  php -r "fwrite(STDERR, 'error message');usleep(50000);fwrite(STDOUT, 'out message');exit(252);"
  RES  252 Command did not run successfully

EOT;
        $syntaxErrorOutputDebug = <<<'EOT'
  RUN  php -r "fwrite(STDERR, 'error message');usleep(500000);fwrite(STDOUT, 'out message');exit(252);"
  ERR  error message
  OUT  out message
  RES  252 Command did not run successfully

EOT;

        $errorMessage = 'An error occurred';
        $args = new Process(['php', '-r', 'echo 42;']);
        $args = $args->getCommandLine();
        $successOutputProcessDebug = str_replace("'php' '-r' 'echo 42;'", $args, $successOutputProcessDebug);

        return [
            ['', 'php -r "echo 42;"', StreamOutput::VERBOSITY_VERBOSE, null],
            [$successOutputVerbose, 'php -r "echo 42;"', StreamOutput::VERBOSITY_VERY_VERBOSE, null],
            [$successOutputDebug, 'php -r "echo 42;"', StreamOutput::VERBOSITY_DEBUG, null],
            [$successOutputDebugWithTags, 'php -r "echo \'<info>42</info>\';"', StreamOutput::VERBOSITY_DEBUG, null],
            ['', 'php -r "syntax error"', StreamOutput::VERBOSITY_VERBOSE, null],
            [$syntaxErrorOutputVerbose, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERY_VERBOSE, null],
            [$syntaxErrorOutputDebug, 'php -r "fwrite(STDERR, \'error message\');usleep(500000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_DEBUG, null],
            [$errorMessage.PHP_EOL, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERBOSE, $errorMessage],
            [$syntaxErrorOutputVerbose.$errorMessage.PHP_EOL, 'php -r "fwrite(STDERR, \'error message\');usleep(50000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_VERY_VERBOSE, $errorMessage],
            [$syntaxErrorOutputDebug.$errorMessage.PHP_EOL, 'php -r "fwrite(STDERR, \'error message\');usleep(500000);fwrite(STDOUT, \'out message\');exit(252);"', StreamOutput::VERBOSITY_DEBUG, $errorMessage],
            [$successOutputProcessDebug, ['php', '-r', 'echo 42;'], StreamOutput::VERBOSITY_DEBUG, null],
            [$successOutputDebug, new Process('php -r "echo 42;"'), StreamOutput::VERBOSITY_DEBUG, null],
        ];
    }

    private function getOutputStream($verbosity)
    {
        return new StreamOutput(fopen('php://memory', 'r+', false), $verbosity, false);
    }

    private function getOutput(StreamOutput $output)
    {
        rewind($output->getStream());

        return stream_get_contents($output->getStream());
    }
}
