<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\StreamHelper;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Command\ServerDumpCommand;
use Symfony\Component\VarDumper\Server\DumpServer;

class ServerDumpCommandTest extends TestCase
{
    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public function testServerDumpSuccess()
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $data = new Data([['my dump']]);
        $input = base64_encode(serialize([$data, ['timestamp' => time(), 'source' => ['name' => 'sourceName', 'line' => 222, 'file' => 'myFile']]]))."\n";

        $commandTester->setInputs([$input]);

        $commandTester->execute(['--format' => 'html']);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('my dump', $output);
    }

    public static function provideCompletionSuggestions()
    {
        yield 'option --format' => [
            ['--format', ''],
            ['cli', 'html'],
        ];
    }

    private function createCommand(): ServerDumpCommand
    {
        $command = new ServerDumpCommand($this->createMock(DumpServer::class));
        $command->setHelperSet(new HelperSet([new StreamHelper()]));

        return $command;
    }
}
