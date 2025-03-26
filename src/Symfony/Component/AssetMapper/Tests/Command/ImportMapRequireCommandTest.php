<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\Command\ImportMapRequireCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\Tests\Fixtures\ImportMapTestAppKernel;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ImportMapRequireCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return ImportMapTestAppKernel::class;
    }

    /**
     * @dataProvider getRequirePackageTests
     */
    public function testDryRunOptionToShowInformationBeforeApplyInstallation(int $verbosity, array $packageEntries, array $packagesToInstall, string $expected, ?string $path = null)
    {
        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager
            ->method('requirePackages')
            ->willReturn($packageEntries)
        ;

        $command = new ImportMapRequireCommand(
            $importMapManager,
            $this->createMock(ImportMapVersionChecker::class),
            '/path/to/project/dir',
        );

        $args = [
            'packages' => $packagesToInstall,
            '--dry-run' => true,
        ];
        if ($path) {
            $args['--path'] = $path;
        }

        $commandTester = new CommandTester($command);
        $commandTester->execute($args, ['verbosity' => $verbosity]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertEquals($this->trimBeginEndOfEachLine($expected), $this->trimBeginEndOfEachLine($output));
    }

    public static function getRequirePackageTests(): iterable
    {
        yield 'require package with dry run and normal verbosity options' => [
            OutputInterface::VERBOSITY_NORMAL,
            [self::createRemoteEntry('bootstrap', '4.2.3', 'assets/vendor/bootstrap/bootstrap.js')],
            ['bootstrap'], <<<EOF
 [DRY-RUN] No changes will apply to the importmap configuration.

 [OK] Package "bootstrap" added to importmap.php.

 Use the new package normally by importing "bootstrap".

 [DRY-RUN] No changes applied to the importmap configuration.
 EOF,
        ];

        yield 'remote package requested with a version with dry run and verbosity verbose options' => [
            OutputInterface::VERBOSITY_VERBOSE,
            [self::createRemoteEntry('bootstrap', '5.3.3', 'assets/vendor/bootstrap/bootstrap.js')],
            ['bootstrap'], <<<EOF
 [DRY-RUN] No changes will apply to the importmap configuration.

 ----------- --------- --------------------------------------
  Package     Version   Path
 ----------- --------- --------------------------------------
  bootstrap   5.3.3     assets/vendor/bootstrap/bootstrap.js
 ----------- --------- --------------------------------------

 [OK] Package "bootstrap" added to importmap.php.

 Use the new package normally by importing "bootstrap".

 [DRY-RUN] No changes applied to the importmap configuration.
 EOF,
        ];

        yield 'local package require a path, with dry run and verbosity verbose options' => [
            OutputInterface::VERBOSITY_VERBOSE,
            [ImportMapEntry::createLocal('alice.js', ImportMapType::JS, 'assets/js/alice.js', false)],
            ['alice.js'], <<<EOF
 [DRY-RUN] No changes will apply to the importmap configuration.

 ---------- --------- --------------------
  Package    Version   Path
 ---------- --------- --------------------
  alice.js   -         assets/js/alice.js
 ---------- --------- --------------------

 [OK] Package "alice.js" added to importmap.php.

 Use the new package normally by importing "alice.js".

 [DRY-RUN] No changes applied to the importmap configuration.
EOF,
            './assets/alice.js',
        ];

        yield 'multi remote packages requested with dry run and verbosity normal options' => [
            OutputInterface::VERBOSITY_NORMAL, [
                self::createRemoteEntry('bootstrap', '5.3.3', 'assets/vendor/bootstrap/bootstrap.index.js'),
                self::createRemoteEntry('lodash', '4.17.20', 'assets/vendor/lodash/lodash.index.js'),
            ],
            ['bootstrap lodash@4.17.21'], <<<EOF
 [DRY-RUN] No changes will apply to the importmap configuration.

 [OK] 2 new items (bootstrap, lodash) added to the importmap.php!

 [DRY-RUN] No changes applied to the importmap configuration.
 EOF,
        ];

        yield 'multi remote packages requested with dry run and verbosity verbose options' => [
            OutputInterface::VERBOSITY_VERBOSE, [
                self::createRemoteEntry('bootstrap', '5.3.3', 'assets/vendor/bootstrap/bootstrap.js'),
                self::createRemoteEntry('lodash', '4.17.20', 'assets/vendor/lodash/lodash.index.js'),
            ],
            ['bootstrap lodash@4.17.21'], <<<EOF
  [DRY-RUN] No changes will apply to the importmap configuration.

 ----------- --------- --------------------------------------
  Package     Version   Path
 ----------- --------- --------------------------------------
  bootstrap   5.3.3     assets/vendor/bootstrap/bootstrap.js
  lodash      4.17.20   assets/vendor/lodash/lodash.index.js
 ----------- --------- --------------------------------------

 [OK] 2 new items (bootstrap, lodash) added to the importmap.php!

 [DRY-RUN] No changes applied to the importmap configuration.
 EOF,
        ];
    }

    public function testNothingChangedOnFilesystemAfterUsingDryRunOption()
    {
        $kernel = self::bootKernel();
        $projectDir = $kernel->getProjectDir();

        $fs = new Filesystem();
        $fs->mkdir($projectDir.'/public');

        $fs->dumpFile($projectDir.'/public/assets/manifest.json', '{}');
        $fs->dumpFile($projectDir.'/public/assets/importmap.json', '{}');

        $importMapManager = $this->createMock(ImportMapManager::class);
        $importMapManager
            ->expects($this->once())
            ->method('requirePackages')
            ->willReturn([self::createRemoteEntry('bootstrap', '5.3.3', 'assets/vendor/bootstrap/bootstrap.index.js')]);

        self::getContainer()->set(ImportMapManager::class, $importMapManager);

        $application = new Application(self::$kernel);
        $command = $application->find('importmap:require');

        $importMapContentBefore = $fs->readFile($projectDir.'/importmap.php');
        $installedVendorBefore = $fs->readFile($projectDir.'/assets/vendor/installed.php');

        $tester = new CommandTester($command);
        $tester->execute(['packages' => ['bootstrap'], '--dry-run' => true]);

        $tester->assertCommandIsSuccessful();

        $this->assertSame($importMapContentBefore, $fs->readFile($projectDir.'/importmap.php'));
        $this->assertSame($installedVendorBefore, $fs->readFile($projectDir.'/assets/vendor/installed.php'));

        $this->assertSame('{}', $fs->readFile($projectDir.'/public/assets/manifest.json'));
        $this->assertSame('{}', $fs->readFile($projectDir.'/public/assets/importmap.json'));

        $finder = new Finder();
        $finder->in($projectDir.'/public/assets')->files()->depth(0);

        $this->assertCount(2, $finder); // manifest.json + importmap.json

        $fs->remove($projectDir.'/public');
        $fs->remove($projectDir.'/var');

        static::$kernel->shutdown();
    }

    private static function createRemoteEntry(string $importName, string $version, ?string $path = null): ImportMapEntry
    {
        return ImportMapEntry::createRemote($importName, ImportMapType::JS, path: $path, version: $version, packageModuleSpecifier: $importName, isEntrypoint: false);
    }

    private function trimBeginEndOfEachLine(string $lines): string
    {
        return trim(implode("\n", array_map('trim', explode("\n", $lines))));
    }
}
