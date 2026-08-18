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
use Symfony\Component\Console\Command\Command;
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

    /**
     * @return iterable<array{string, string|null}>
     */
    public static function provideNoOutdatedPackageCases(): iterable
    {
        yield 'default' => ['No updates found.', null];
        yield 'txt' => ['No updates found.', 'txt'];
        yield 'json' => ['[]', 'json'];
    }

    public function testCommandReportsTheVersionHeldBackByTheMinimumReleaseAge()
    {
        $updateChecker = $this->createStub(ImportMapUpdateChecker::class);
        $updateChecker->method('getMinimumReleaseAge')->willReturn(604800);
        $updateChecker->method('getAvailableUpdates')->willReturn([
            'bootstrap' => new PackageUpdateInfo('bootstrap', '5.3.1', '5.3.2', PackageUpdateInfo::UPDATE_TYPE_PATCH, '5.3.3'),
        ]);

        $commandTester = new CommandTester(new ImportMapOutdatedCommand($updateChecker));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay(true);
        $this->assertSame(Command::FAILURE, $commandTester->getStatusCode());
        $this->assertStringContainsString('Withheld', $display);
        $this->assertMatchesRegularExpression('/bootstrap\s+5\.3\.1\s+5\.3\.2\s+5\.3\.3/', $display);
    }

    public function testCommandListsAPackageWhoseOnlyUpdateIsHeldBack()
    {
        $updateChecker = $this->createStub(ImportMapUpdateChecker::class);
        $updateChecker->method('getMinimumReleaseAge')->willReturn(604800);
        $updateChecker->method('getAvailableUpdates')->willReturn([
            'bootstrap' => new PackageUpdateInfo('bootstrap', '5.3.1', '5.3.1', PackageUpdateInfo::UPDATE_TYPE_UP_TO_DATE, '5.3.2'),
        ]);

        $commandTester = new CommandTester(new ImportMapOutdatedCommand($updateChecker));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('5.3.2', $commandTester->getDisplay(true));
    }

    public function testCommandOmitsTheWithheldColumnWhenTheMinimumReleaseAgeIsDisabled()
    {
        $updateChecker = $this->createStub(ImportMapUpdateChecker::class);
        $updateChecker->method('getMinimumReleaseAge')->willReturn(0);
        $updateChecker->method('getAvailableUpdates')->willReturn([
            'bootstrap' => new PackageUpdateInfo('bootstrap', '5.3.1', '5.3.3', PackageUpdateInfo::UPDATE_TYPE_PATCH),
        ]);

        $commandTester = new CommandTester(new ImportMapOutdatedCommand($updateChecker));
        $commandTester->execute(['--format' => 'json']);

        $this->assertSame(
            [['name' => 'bootstrap', 'current' => '5.3.1', 'latest' => '5.3.3', 'latest-status' => 'semver-safe-update']],
            json_decode($commandTester->getDisplay(true), true)
        );
    }
}
