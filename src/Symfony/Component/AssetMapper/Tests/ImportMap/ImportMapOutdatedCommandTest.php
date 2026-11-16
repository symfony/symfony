<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\ImportMap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Command\ImportMapOutdatedCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMapOutdatedCommandTest extends TestCase
{
    #[DataProvider('provideNoOutdatedPackageCases')]
    public function testCommandWhenNoOutdatedPackages(string $display, ?string $format = null)
    {
        $updateChecker = $this->createStub(ImportMapUpdateChecker::class);
        $command = new ImportMapOutdatedCommand($updateChecker);

        $commandTester = new CommandTester($command);
        $commandTester->execute(\is_string($format) ? ['--format' => $format] : []);

        $commandTester->assertCommandIsSuccessful();
        $this->assertEquals($display, trim($commandTester->getDisplay(true)));
    }

    public function testCommandWithMarkupInJsonFormat()
    {
        $updateChecker = $this->createMock(ImportMapUpdateChecker::class);
        $updateChecker->expects($this->once())
            ->method('getAvailableUpdates')
            ->willReturn([
                'pkg<info>' => new PackageUpdateInfo('pkg<info>', '1.0.0', '2.0.0', PackageUpdateInfo::UPDATE_TYPE_MAJOR),
            ]);
        $command = new ImportMapOutdatedCommand($updateChecker);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json'], ['decorated' => true]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $json = json_decode($commandTester->getDisplay(), true);
        $this->assertIsArray($json);
        $this->assertSame('pkg<info>', $json[0]['name']);
    }

    public function testCommandWithMarkupInTextFormat()
    {
        $updateChecker = $this->createMock(ImportMapUpdateChecker::class);
        $updateChecker->expects($this->once())
            ->method('getAvailableUpdates')
            ->willReturn([
                'pkg<info>' => new PackageUpdateInfo('pkg<info>', '1.0.0', '2.0.0', PackageUpdateInfo::UPDATE_TYPE_MAJOR),
            ]);
        $command = new ImportMapOutdatedCommand($updateChecker);

        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['decorated' => false]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('pkg<info>', $commandTester->getDisplay());
    }

    public function testCommandWithInvalidUtf8InJsonFormat()
    {
        $updateChecker = $this->createMock(ImportMapUpdateChecker::class);
        $updateChecker->expects($this->once())
            ->method('getAvailableUpdates')
            ->willReturn([
                "pkg\xFF" => new PackageUpdateInfo("pkg\xFF", '1.0.0', '2.0.0', PackageUpdateInfo::UPDATE_TYPE_MAJOR),
            ]);

        $commandTester = new CommandTester(new ImportMapOutdatedCommand($updateChecker));
        $commandTester->execute(['--format' => 'json'], ['decorated' => false]);

        $this->assertSame("pkg\u{FFFD}", json_decode($commandTester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR)[0]['name']);
    }

    /**
     * @return iterable<array{string, string|null}>
     */
    public static function provideNoOutdatedPackageCases(): iterable
    {
        yield 'default' => ['No updates found.', null];
        yield 'txt' => ['No updates found.', 'txt'];
        yield 'json' => ['[]', 'json'];
    }
}
