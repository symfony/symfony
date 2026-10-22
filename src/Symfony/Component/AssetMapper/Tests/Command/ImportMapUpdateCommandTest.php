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

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Command\ImportMapUpdateCommand;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageVersionProblem;
use Symfony\Component\Console\Tester\CommandTester;

class ImportMapUpdateCommandTest extends TestCase
{
    #[BackupGlobals(true)]
    #[TestWith([false])]
    #[TestWith([true])]
    public function testMissingDependencySuggestsTheRequireCommand(bool $formatterTags)
    {
        if ($formatterTags && '\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows paths cannot contain formatter tags.');
        }

        $_SERVER['SCRIPT_FILENAME'] = __FILE__;
        $_SERVER['SYMFONY_CLI_CONSOLE_COMMAND'] = json_encode([$formatterTags ? 'symfony<info>' : 'symfony', 'console']);
        $_SERVER['SYMFONY_CLI_CONSOLE_SCRIPT'] = __FILE__;

        $manager = $this->createStub(ImportMapManager::class);
        $manager->method('update')->willReturn([]);
        $checker = $this->createStub(ImportMapVersionChecker::class);
        $checker->method('checkVersions')->willReturn([new PackageVersionProblem('bootstrap', '@popperjs/core', '^2.0', null)]);

        $tester = new CommandTester(new ImportMapUpdateCommand($manager, $checker));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('You may need to run "'.($formatterTags ? "'symfony<info>'" : 'symfony').' console importmap:require @popperjs/core".', $tester->getDisplay());
    }
}
