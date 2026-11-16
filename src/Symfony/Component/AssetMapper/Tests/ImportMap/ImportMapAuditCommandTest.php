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

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Command\ImportMapAuditCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\AssetMapper\ImportMap\ImportMapPackageAudit;
use Symfony\Component\AssetMapper\ImportMap\ImportMapPackageAuditVulnerability;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMapAuditCommandTest extends TestCase
{
    public function testAuditJsonWithDecoratedOutput()
    {
        $auditor = $this->createMock(ImportMapAuditor::class);
        $auditor->expects($this->once())
            ->method('audit')
            ->willReturn([
                new ImportMapPackageAudit('pkg<info>', '1.0.0', [
                    new ImportMapPackageAuditVulnerability('GHSA-1234', 'CVE-1234', 'https://example.com', 'Summary with <comment>tags</comment>', 'high', '^1.0', '1.0.1'),
                ]),
            ]);

        $command = new ImportMapAuditCommand($auditor);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json'], ['decorated' => true]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $json = json_decode($commandTester->getDisplay(), true);
        $this->assertIsArray($json);
        $this->assertSame('pkg<info>', $json['packages'][0]['package']);
        $this->assertSame('Summary with <comment>tags</comment>', $json['packages'][0]['vulnerabilities'][0]['summary']);
    }

    public function testAuditTextWithMarkup()
    {
        $auditor = $this->createMock(ImportMapAuditor::class);
        $auditor->expects($this->once())
            ->method('audit')
            ->willReturn([
                new ImportMapPackageAudit('pkg<info>', '1.0.0', [
                    new ImportMapPackageAuditVulnerability('GHSA-1234', 'CVE-1234', 'https://example.com', 'Summary with <comment>tags</comment>', 'high', '^1.0', '1.0.1'),
                ]),
            ]);

        $command = new ImportMapAuditCommand($auditor);
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['decorated' => false]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $display = $commandTester->getDisplay();
        $this->assertStringContainsString('pkg<info>', $display);
        $this->assertStringContainsString('Summary with <comment>tags</comment>', $display);
    }

    public function testAuditTextWithMarkupInPackageWithoutVersion()
    {
        $auditor = $this->createMock(ImportMapAuditor::class);
        $auditor->expects($this->once())
            ->method('audit')
            ->willReturn([new ImportMapPackageAudit('pkg<info>', null)]);

        $commandTester = new CommandTester(new ImportMapAuditCommand($auditor));
        $commandTester->execute([], ['decorated' => false]);

        $this->assertStringContainsString('[WARNING] Unable to retrieve versions for package: pkg<info>', $commandTester->getDisplay());
    }

    public function testAuditJsonWithInvalidUtf8()
    {
        $auditor = $this->createMock(ImportMapAuditor::class);
        $auditor->expects($this->once())
            ->method('audit')
            ->willReturn([new ImportMapPackageAudit("pkg\xFF", '1.0.0')]);

        $commandTester = new CommandTester(new ImportMapAuditCommand($auditor));
        $commandTester->execute(['--format' => 'json'], ['decorated' => false]);

        $this->assertSame("pkg\u{FFFD}", json_decode($commandTester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR)['packages'][0]['package']);
    }
}
