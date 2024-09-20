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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;
use Symfony\Component\Console\Tester\CommandTester;

class DebugAssetsMapperCommandTest extends TestCase
{
    public function testCommandDumpsInformation()
    {
        $application = new Application(new AssetMapperTestAppKernel('test', true));

        $command = $application->find('debug:asset-map');
        $tester = new CommandTester($command);
        $res = $tester->execute([]);
        $this->assertSame(0, $res);

        $this->assertStringContainsString('dir1', $tester->getDisplay());
        $this->assertStringContainsString('subdir/file6.js', $tester->getDisplay());
        $this->assertStringContainsString('dir2'.\DIRECTORY_SEPARATOR.'subdir'.\DIRECTORY_SEPARATOR.'file6.js', $tester->getDisplay());
    }
}
