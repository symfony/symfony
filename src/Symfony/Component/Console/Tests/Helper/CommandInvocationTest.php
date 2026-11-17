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

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\CommandInvocation;

#[BackupGlobals(true)]
class CommandInvocationTest extends TestCase
{
    #[TestWith([false])]
    #[TestWith([true])]
    public function testCurrentAndSiblingCommands(bool $singleCommand)
    {
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $command = new Command('secrets:list');
        $application = new Application();
        $application->addCommand($command);
        $application->setDefaultCommand('secrets:list', $singleCommand);

        $this->assertSame('symfony console'.($singleCommand ? '' : ' secrets:list'), CommandInvocation::format($command));
        $this->assertSame('symfony console secrets:set', CommandInvocation::format('secrets:set'));
        $this->assertSame('symfony console', CommandInvocation::format());
    }

    public function testReturnsRawShellText()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows paths cannot contain angle brackets.');
        }

        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["/tmp/<info>/symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $this->assertSame("'/tmp/<info>/symfony' console cache:clear", CommandInvocation::format('cache:clear'));
    }

    public function testEscapedInvocationPreservesFormatterTags()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows paths cannot contain angle brackets.');
        }

        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["/tmp/<info>/symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $invocation = CommandInvocation::formatEscaped(new Command('cache:clear'));

        $this->assertSame("'/tmp/<info>/symfony' console cache:clear", (new OutputFormatter())->format('<comment>'.$invocation.'</comment>'));
    }

    public function testUnnamedCommandOnlyReturnsThePrefix()
    {
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $this->assertSame('symfony console', CommandInvocation::format(new Command()));
    }

    public function testExecutableScriptRunsWithoutPhp()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Scripts cannot be made executable on Windows.');
        }

        $script = tempnam(sys_get_temp_dir(), 'console-invocation-');
        chmod($script, 0o755);

        try {
            $_SERVER['PHP_SELF'] = $script;
            unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

            $this->assertSame($script.' cache:clear', CommandInvocation::format('cache:clear'));
        } finally {
            unlink($script);
        }
    }

    public function testNonExecutableScriptRunsWithPhp()
    {
        $script = tempnam(sys_get_temp_dir(), 'console-invocation-');
        chmod($script, 0o644);

        try {
            $_SERVER['PHP_SELF'] = $script;
            unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

            $this->assertSame('php '.$script.' cache:clear', CommandInvocation::format('cache:clear'));
        } finally {
            unlink($script);
        }
    }

    public function testExecutableScriptWithoutDirectoryRunsWithPhp()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Scripts cannot be made executable on Windows.');
        }

        $script = tempnam(sys_get_temp_dir(), 'console-invocation-');
        chmod($script, 0o755);
        $cwd = getcwd();
        chdir(\dirname($script));

        try {
            $_SERVER['PHP_SELF'] = basename($script);
            unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

            $this->assertSame('php '.basename($script).' cache:clear', CommandInvocation::format('cache:clear'));
        } finally {
            chdir($cwd);
            unlink($script);
        }
    }

    public function testFallbackScriptForStandaloneCallers()
    {
        $script = tempnam(sys_get_temp_dir(), 'console-invocation-');
        chmod($script, 0o755);

        try {
            $_SERVER['PHP_SELF'] = $script;
            $_SERVER['SCRIPT_FILENAME'] = __FILE__;
            unset($_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'], $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT']);

            $this->assertSame('php bin/console importmap:install', CommandInvocation::format('importmap:install', 'bin/console'));
        } finally {
            unlink($script);
        }
    }

    public function testLauncherTakesPrecedenceOverTheFallbackScript()
    {
        $_SERVER['PHP_SELF'] = 'custom-console.php';
        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $this->assertSame('symfony console importmap:install', CommandInvocation::format('importmap:install', 'bin/console'));
    }

    public function testFallbackScriptCannotAuthenticateTheLauncher()
    {
        $_SERVER['PHP_SELF'] = __DIR__.'/../Fixtures/TestCommand.php';
        unset($_SERVER['SCRIPT_FILENAME']);
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = '["symfony","console"]';
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $this->assertStringNotContainsString('symfony console', CommandInvocation::format('cache:clear', __FILE__));
    }
}
