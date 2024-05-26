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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;

class DotenvDumpCommandTest extends TestCase
{
    use ExpectDeprecationTrait;

    protected function setUp(): void
    {
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
        @unlink(__DIR__.'/.env.local.php');
        @unlink(__DIR__.'/.env.dumped.php');
        @unlink(__DIR__.'/composer.json');
    }

    public function testExecute()
    {
        $command = $this->createCommand();
        $command->execute([
            'env' => 'test',
            '--preferPhpFilesAndChangeDumpName' => null,
        ]);

        $this->assertFileExists(__DIR__.'/.env.dumped.php');

        $vars = require __DIR__.'/.env.dumped.php';
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
            '--preferPhpFilesAndChangeDumpName' => null,
        ]);

        $this->assertFileExists(__DIR__.'/.env.dumped.php');

        $vars = require __DIR__.'/.env.dumped.php';
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
            '--preferPhpFilesAndChangeDumpName' => null,
        ]);

        $this->assertFileExists(__DIR__.'/.env.dumped.php');

        $vars = require __DIR__.'/.env.dumped.php';
        $this->assertSame([
            'APP_ENV' => 'test',
            'APP_SECRET' => 'abc123',
            'APP_LOCAL' => 'yes',
        ], $vars);
    }

    /**
     * @group legacy
     */
    public function testDeprecationForNotPreferringPhpFiles()
    {
        $this->expectDeprecation('Since symfony/dotenv 7.2: Not using --preferPhpFilesAndChangeDumpName is deprecated.');
        $this->expectDeprecation('Since symfony/dotenv 7.2: Setting $preferPhpFilesAndChangeDumpName to false is deprecated.');
        $command = $this->createCommand();
        $command->execute([
            'env' => 'test',
        ]);
    }

    private function createCommand(): CommandTester
    {
        $application = new Application();
        $application->add(new DotenvDumpCommand(__DIR__));

        return new CommandTester($application->find('dotenv:dump'));
    }
}
