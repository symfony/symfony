<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Command;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Command\ServerLogCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\StreamHelper;
use Symfony\Component\Console\Tester\CommandTester;

class ServerLogCommandTest extends TestCase
{
    public function testServerLogSuccess()
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $record = new LogRecord(
            new \DateTimeImmutable('2024-01-02 18:05'),
            'console',
            Level::Info,
            'test log command',
        );
        $recordFormatted = $record->toArray();
        $input = base64_encode(serialize($recordFormatted))."\n";

        $commandTester->setInputs([$input]);

        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('18:05:00 INFO      [console] test log command', $output);
    }

    private function createCommand(): ServerLogCommand
    {
        $command = new ServerLogCommand();
        $command->setHelperSet(new HelperSet([new StreamHelper()]));

        return $command;
    }
}
