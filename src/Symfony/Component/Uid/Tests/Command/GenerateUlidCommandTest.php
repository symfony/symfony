<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Command\GenerateUlidCommand;
use Symfony\Component\Uid\Ulid;

final class GenerateUlidCommandTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testDefaults()
    {
        $time = microtime(false);
        $time = substr($time, 11).substr($time, 1, 4);

        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(0, $commandTester->execute([]));

        $ulid = Ulid::fromBase32(trim($commandTester->getDisplay()));
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', $time), $ulid->getDateTime());
    }

    public function testUnparsableTimestamp()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(1, $commandTester->execute(['--time' => 'foo']));
        $this->assertStringContainsString('Invalid timestamp "foo"', $commandTester->getDisplay());
    }

    public function testTimestampBeforeUnixEpoch()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(1, $commandTester->execute(['--time' => '@-42']));
        $this->assertStringContainsString('The timestamp must be positive', $commandTester->getDisplay());
    }

    public function testTimestamp()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(0, $commandTester->execute(['--time' => '2021-02-16 18:09:42.999']));

        $ulid = Ulid::fromBase32(trim($commandTester->getDisplay()));
        $this->assertEquals(new \DateTimeImmutable('2021-02-16 18:09:42.999'), $ulid->getDateTime());
    }

    public function testCount()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(0, $commandTester->execute(['--count' => '10']));

        $ulids = explode("\n", trim($commandTester->getDisplay(true)));
        $this->assertCount(10, $ulids);
        foreach ($ulids as $ulid) {
            $this->assertTrue(Ulid::isValid($ulid));
        }
    }

    public function testInvalidFormat()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(1, $commandTester->execute(['--format' => 'foo']));
        $this->assertStringContainsString('Invalid format "foo"', $commandTester->getDisplay());
    }

    public function testFormat()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(0, $commandTester->execute(['--format' => 'rfc4122']));

        Ulid::fromRfc4122(trim($commandTester->getDisplay()));
    }

    public function testUlidsAreDifferentWhenGeneratingSeveralNow()
    {
        $commandTester = new CommandTester(new GenerateUlidCommand());

        $this->assertSame(0, $commandTester->execute(['--time' => 'now', '--count' => '2']));

        $ulids = explode("\n", trim($commandTester->getDisplay(true)));

        $this->assertNotSame($ulids[0], $ulids[1]);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->add(new GenerateUlidCommand());
        $tester = new CommandCompletionTester($application->get('ulid:generate'));
        $suggestions = $tester->complete($input, 2);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'option --format' => [
            ['--format', ''],
            ['base32', 'base58', 'rfc4122'],
        ];
    }
}
