<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dotenv\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;

class DotenvDumpCommandTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['SYMFONY_DOTENV_PATH']);
        unset($_SERVER['APP_RUNTIME_OPTIONS']);

        file_put_contents(__DIR__.'/.env', <<<EOF
            APP_ENV=dev
            APP_SECRET=abc123
            EOF
        );

        file_put_contents(__DIR__.'/.env.local', <<<EOF
            APP_LOCAL=yes
            EOF
        );
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__.'/.env');
        @unlink(__DIR__.'/.env.local');
        @unlink(__DIR__.'/.env.path');
        @unlink(__DIR__.'/.env.path.local');
        @unlink(__DIR__.'/.env.local.php');
        @unlink(__DIR__.'/.env.path.local.php');
        @unlink(__DIR__.'/composer.json');

        unset($_SERVER['SYMFONY_DOTENV_PATH']);
        unset($_SERVER['APP_RUNTIME_OPTIONS']);
    }

    public function testExecute()
    {
        $command = $this->createCommand();
        $command->execute([
            'env' => 'test',
        ]);

        $this->assertFileExists(__DIR__.'/.env.local.php');

        $vars = require __DIR__.'/.env.local.php';
        $this->assertSame([
            'APP_ENV' => 'test',
            'APP_SECRET' => 'abc123',
        ], $vars);
    }

    public function testExecuteEmpty()
    {
        $command = $this->createCommand();
        $command->execute([
            'env' => 'test',
            '--empty' => true,
        ]);

        $this->assertFileExists(__DIR__.'/.env.local.php');

        $vars = require __DIR__.'/.env.local.php';
        $this->assertSame(['APP_ENV' => 'test'], $vars);
    }

    public function testExecuteTestEnvs()
    {
        file_put_contents(__DIR__.'/composer.json', <<<EOF
            {"extra":{"runtime":{"test_envs":[]}}}
            EOF
        );

        $command = $this->createCommand();
        $command->execute([
            'env' => 'test',
        ]);

        $this->assertFileExists(__DIR__.'/.env.local.php');

        $vars = require __DIR__.'/.env.local.php';
        $this->assertSame([
            'APP_ENV' => 'test',
            'APP_SECRET' => 'abc123',
            'APP_LOCAL' => 'yes',
        ], $vars);
    }

    public function testExecuteWithRuntimeOptionsDotenvPath()
    {
        file_put_contents(__DIR__.'/.env.path', <<<EOF
            APP_ENV=test
            APP_SECRET=newpath123
            EOF
        );
        file_put_contents(__DIR__.'/.env.path.local', <<<EOF
            LOCAL_PATH=yes
            EOF
        );

        $_SERVER['APP_RUNTIME_OPTIONS'] = ['dotenv_path' => '.env.path'];

        $command = $this->createCommand();
        $command->execute([
            'env' => 'dev',
        ]);

        $this->assertFileExists(__DIR__.'/.env.path.local.php');

        $vars = require __DIR__.'/.env.path.local.php';
        $this->assertSame([
            'APP_ENV' => 'dev',
            'APP_SECRET' => 'newpath123',
            'LOCAL_PATH' => 'yes',
        ], $vars);
    }

    private function createCommand(): CommandTester
    {
        $application = new Application();
        $application->addCommand(new DotenvDumpCommand(__DIR__));

        return new CommandTester($application->find('dotenv:dump'));
    }
}
