<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Debug;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\TraceableCommand;
use Symfony\Component\Console\Debug\CliRequest;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Stopwatch\Stopwatch;

#[BackupGlobals(true)]
class CliRequestTest extends TestCase
{
    public function testUriPreservesTheConsoleLauncher()
    {
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["custom-symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $command = new TraceableCommand(new Command('cache:clear'), new Stopwatch());
        $command->input = new StringInput('cache:clear --no-warmup');
        $request = new CliRequest($command);
        unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

        $this->assertSame('custom-symfony console '.$command->input, $request->getUri());
    }

    public function testUriIgnoresTheBinaryMarkerForPhpCommands()
    {
        $_SERVER['PHP_SELF'] = 'bin/worker';
        $_SERVER['argv'] = ['bin/worker', 'cache:clear'];
        $_SERVER['SYMFONY_CLI_BINARY_NAME'] = 'symfony';
        unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

        $command = new TraceableCommand(new Command('cache:clear'), new Stopwatch());
        $command->input = new StringInput('cache:clear');

        $this->assertStringEndsWith(' bin/worker '.$command->input, (new CliRequest($command))->getUri());
    }
}
