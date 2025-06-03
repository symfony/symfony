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
use Symfony\Component\Uid\Command\GenerateUuidCommand;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

final class GenerateUuidCommandTest extends TestCase
{
    public function testDefaults()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());
        $this->assertSame(0, $commandTester->execute([]));
        $this->assertInstanceOf(UuidV6::class, Uuid::fromRfc4122(trim($commandTester->getDisplay())));

        $commandTester = new CommandTester(new GenerateUuidCommand(new UuidFactory(UuidV4::class)));
        $this->assertSame(0, $commandTester->execute([]));
        $this->assertInstanceOf(UuidV4::class, Uuid::fromRfc4122(trim($commandTester->getDisplay())));
    }

    public function testTimeBasedWithInvalidNode()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--time-based' => 'now', '--node' => 'foo']));
        $this->assertStringContainsString('Invalid node "foo"', $commandTester->getDisplay());
    }

    public function testTimeBasedWithUnparsableTimestamp()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--time-based' => 'foo']));
        $this->assertStringContainsString('Invalid timestamp "foo"', $commandTester->getDisplay());
    }

    public function testTimeBasedWithTimestampBeforeUUIDEpoch()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--time-based' => '@-16807797990']));
        $this->assertStringContainsString('The given UUID date cannot be earlier than 1582-10-15.', $commandTester->getDisplay());
    }

    public function testTimeBased()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());
        $this->assertSame(0, $commandTester->execute(['--time-based' => 'now']));
        $this->assertInstanceOf(UuidV6::class, Uuid::fromRfc4122(trim($commandTester->getDisplay())));

        $commandTester = new CommandTester(new GenerateUuidCommand(new UuidFactory(
            UuidV6::class,
            UuidV1::class,
            UuidV5::class,
            UuidV4::class,
            'b2ba9fa1-d84a-4d49-bb0a-691421b27a00'
        )));
        $this->assertSame(0, $commandTester->execute(['--time-based' => '2000-01-02 19:09:17.871524 +00:00']));
        $uuid = Uuid::fromRfc4122(trim($commandTester->getDisplay()));
        $this->assertInstanceOf(UuidV1::class, $uuid);
        $this->assertStringMatchesFormat('1c31e868-c148-11d3-%s-691421b27a00', (string) $uuid);
    }

    public function testNameBasedWithInvalidNamespace()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--name-based' => 'foo', '--namespace' => 'bar']));
        $this->assertStringContainsString('Invalid namespace "bar"', $commandTester->getDisplay());
    }

    public function testNameBasedWithoutNamespace()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--name-based' => 'foo']));
        $this->assertStringContainsString('Missing namespace', $commandTester->getDisplay());
    }

    public function testNameBased()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());
        $this->assertSame(0, $commandTester->execute(['--name-based' => 'foo', '--namespace' => 'bcdf2a0e-e287-4d20-a92f-103eda39b100']));
        $this->assertInstanceOf(UuidV5::class, Uuid::fromRfc4122(trim($commandTester->getDisplay())));

        $commandTester = new CommandTester(new GenerateUuidCommand(new UuidFactory(
            UuidV6::class,
            UuidV1::class,
            UuidV3::class,
            UuidV4::class,
            null,
            '6fc5292a-5f9f-4ada-94a4-c4063494d657'
        )));
        $this->assertSame(0, $commandTester->execute(['--name-based' => 'bar']));
        $this->assertEquals(new UuidV3('54950ff1-375c-33e8-a992-2109e384091f'), Uuid::fromRfc4122(trim($commandTester->getDisplay())));
    }

    public function testRandomBased()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());
        $this->assertSame(0, $commandTester->execute(['--random-based' => null]));
        $this->assertInstanceOf(UuidV4::class, Uuid::fromRfc4122(trim($commandTester->getDisplay())));
    }

    /**
     * @dataProvider provideInvalidCombinationOfBasedOptions
     */
    public function testInvalidCombinationOfBasedOptions(array $input)
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute($input));
        $this->assertStringContainsString('Only one of "--time-based", "--name-based" or "--random-based"', $commandTester->getDisplay());
    }

    public static function provideInvalidCombinationOfBasedOptions(): array
    {
        return [
            [['--time-based' => 'now', '--name-based' => 'foo']],
            [['--time-based' => 'now', '--random-based' => null]],
            [['--name-based' => 'now', '--random-based' => null]],
            [['--time-based' => 'now', '--name-based' => 'now', '--random-based' => null]],
        ];
    }

    /**
     * @dataProvider provideExtraNodeOption
     */
    public function testExtraNodeOption(array $input)
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute($input));
        $this->assertStringContainsString('Option "--node" can only be used with "--time-based"', $commandTester->getDisplay());
    }

    public static function provideExtraNodeOption(): array
    {
        return [
            [['--node' => 'foo']],
            [['--name-based' => 'now', '--node' => 'foo']],
            [['--random-based' => null, '--node' => 'foo']],
        ];
    }

    /**
     * @dataProvider provideExtraNamespaceOption
     */
    public function testExtraNamespaceOption(array $input)
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute($input));
        $this->assertStringContainsString('Option "--namespace" can only be used with "--name-based"', $commandTester->getDisplay());
    }

    public static function provideExtraNamespaceOption(): array
    {
        return [
            [['--namespace' => 'foo']],
            [['--time-based' => 'now', '--namespace' => 'foo']],
            [['--random-based' => null, '--namespace' => 'foo']],
        ];
    }

    public function testCount()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(0, $commandTester->execute(['--count' => '10']));

        $uuids = explode("\n", trim($commandTester->getDisplay(true)));
        $this->assertCount(10, $uuids);
        foreach ($uuids as $uuid) {
            $this->assertTrue(Uuid::isValid($uuid));
        }
    }

    public function testInvalidFormat()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(1, $commandTester->execute(['--format' => 'foo']));
        $this->assertStringContainsString('Invalid format "foo"', $commandTester->getDisplay());
    }

    public function testFormat()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(0, $commandTester->execute(['--format' => 'base32']));

        Uuid::fromBase32(trim($commandTester->getDisplay()));
    }

    public function testTimestampIncrementWhenGeneratingSeveralTimeBasedUuids()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(0, $commandTester->execute(['--time-based' => 'now', '--count' => '2']));

        $uuids = explode("\n", trim($commandTester->getDisplay(true)));

        $this->assertNotSame($uuids[0], $uuids[1]);
    }

    public function testNamespacePredefinedKeyword()
    {
        $commandTester = new CommandTester(new GenerateUuidCommand());

        $this->assertSame(0, $commandTester->execute(['--name-based' => 'https://symfony.com', '--namespace' => 'url']));

        $this->assertSame('9c7d0eda-982d-5708-b4bd-79b3b179725d', (string) Uuid::fromRfc4122(trim($commandTester->getDisplay())));
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->add(new GenerateUuidCommand());
        $tester = new CommandCompletionTester($application->get('uuid:generate'));
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
