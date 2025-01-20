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
use Symfony\Component\AssetMapper\Command\ImportMapOutdatedCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMapOutdatedCommandTest extends TestCase
{
    /**
     * @dataProvider provideNoOutdatedPackageCases
     */
    public function testCommandWhenNoOutdatedPackages(string $display, ?string $format = null)
    {
        $updateChecker = $this->createMock(ImportMapUpdateChecker::class);
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
}
