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

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\AssetMapper\Command\ImportMapExportRemoteCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JsDelivrEsmResolver;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageUrlResolverInterface;
use Symfony\Component\AssetMapper\Tests\Fixtures\ImportMapTestAppKernel;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMapExportRemoteCommandTest extends TestCase
{
    public function testExportsRemotePackagesForAnHtmlPage()
    {
        $entries = new ImportMapEntries([
            ImportMapEntry::createLocal('app', ImportMapType::JS, './assets/app.js', true),
            ImportMapEntry::createRemote('charts', ImportMapType::JS, './assets/vendor/chart.js/auto.js', '4.4.0', 'chart.js/auto', false),
            ImportMapEntry::createRemote('bootstrap/dist/css/bootstrap.min.css', ImportMapType::CSS, './assets/vendor/bootstrap.css', '5.3.2', 'bootstrap/dist/css/bootstrap.min.css', false),
            ImportMapEntry::createRemote('@hotwired/stimulus', ImportMapType::JS, './assets/vendor/stimulus.js', '3.2.1', '@hotwired/stimulus', false),
            ImportMapEntry::createRemote('raw', ImportMapType::JS, './assets/vendor/raw.js', '1.2.3', 'raw-lib/dist/index.js', false, false),
            ImportMapEntry::createRemote('es-module-shims', ImportMapType::JS, './assets/vendor/shims.js', '1.10.0', 'es-module-shims', false),
        ]);
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn($entries);

        $tester = new CommandTester(new ImportMapExportRemoteCommand($reader, new JsDelivrEsmResolver()));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $html = $tester->getDisplay();
        $this->assertStringContainsString('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">', $html);
        $this->assertSame(1, preg_match('~<script type="importmap">\s*(.*?)\s*</script>~s', $html, $matches));
        $this->assertSame([
            'imports' => [
                '@hotwired/stimulus' => 'https://cdn.jsdelivr.net/npm/@hotwired/stimulus@3.2.1/+esm',
                'bootstrap/dist/css/bootstrap.min.css' => 'data:application/javascript,',
                'charts' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/auto/+esm',
                'es-module-shims' => 'https://cdn.jsdelivr.net/npm/es-module-shims@1.10.0',
                'raw' => 'https://cdn.jsdelivr.net/npm/raw-lib@1.2.3/dist/index.js',
            ],
        ], json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('/assets/', $html);
    }

    public function testExportsAnEmptyMapWhenThereAreNoRemotePackages()
    {
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn(new ImportMapEntries([
            ImportMapEntry::createLocal('app', ImportMapType::JS, './assets/app.js', true),
        ]));

        $tester = new CommandTester(new ImportMapExportRemoteCommand($reader, new JsDelivrEsmResolver()));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->assertSame("<script type=\"importmap\">\n{\n    \"imports\": {}\n}\n</script>\n", str_replace("\r\n", "\n", $tester->getDisplay()));
    }

    public function testEscapesHtmlInTheOutput()
    {
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn(new ImportMapEntries([
            ImportMapEntry::createRemote('</script><script>alert(1)</script>', ImportMapType::JS, './vendor/unsafe.js', '1.0.0', 'unsafe', false),
            ImportMapEntry::createRemote('style', ImportMapType::CSS, './vendor/style.css', '1.0.0" onload="alert(1)', 'style.css', false),
        ]));

        $tester = new CommandTester(new ImportMapExportRemoteCommand($reader, new JsDelivrEsmResolver()));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('href="https://cdn.jsdelivr.net/npm/style.css@1.0.0&quot; onload=&quot;alert(1)"', $tester->getDisplay());
        $this->assertStringContainsString('\\u003C/script\\u003E', $tester->getDisplay());
        $this->assertStringNotContainsString('</script><script>', $tester->getDisplay());
    }

    public function testRejectsRemoteJsonEntries()
    {
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn(new ImportMapEntries([
            ImportMapEntry::createRemote('data', ImportMapType::JSON, './vendor/data.json', '1.0.0', 'package/data.json', false),
        ]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The remote JSON entry "data" cannot be exported.');

        (new CommandTester(new ImportMapExportRemoteCommand($reader, new JsDelivrEsmResolver())))->execute([]);
    }

    public function testUsesTheConfiguredPackageResolver()
    {
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn(new ImportMapEntries([
            ImportMapEntry::createRemote('example', ImportMapType::JS, './vendor/example.js', '1.2.3', 'example', false),
        ]));
        $resolver = new class implements PackageResolverInterface, PackageUrlResolverInterface {
            public function resolvePackages(array $packagesToRequire): array
            {
                return [];
            }

            public function downloadPackages(array $importMapEntries, ?callable $progressCallback = null): array
            {
                return [];
            }

            public function getPackageUrl(ImportMapEntry $entry): string
            {
                return 'https://cdn.example.test/'.$entry->importName.'@'.$entry->version.'.js';
            }
        };

        $tester = new CommandTester(new ImportMapExportRemoteCommand($reader, $resolver));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('https://cdn.example.test/example@1.2.3.js', $tester->getDisplay());
        $this->assertStringNotContainsString('jsdelivr', $tester->getDisplay());
    }

    public function testRejectsResolversWithoutUrls()
    {
        $reader = $this->createStub(ImportMapConfigReader::class);
        $reader->method('getEntries')->willReturn(new ImportMapEntries([
            ImportMapEntry::createRemote('example', ImportMapType::JS, './vendor/example.js', '1.2.3', 'example', false),
        ]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The configured package resolver must implement "'.PackageUrlResolverInterface::class.'" to export URLs.');

        (new CommandTester(new ImportMapExportRemoteCommand($reader, $this->createStub(PackageResolverInterface::class))))->execute([]);
    }

    #[RunInSeparateProcess]
    public function testCommandIsRegistered()
    {
        $kernel = new ImportMapTestAppKernel('export_remote', true);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('importmap:export-remote'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('https://cdn.jsdelivr.net/npm/lodash@4.17.21/+esm', $tester->getDisplay());
        $this->assertStringNotContainsString('/assets/', $tester->getDisplay());

        $kernel->shutdown();
    }
}
