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

    public function testCommandFiltersName()
    {
        $application = new Application(new AssetMapperTestAppKernel('test', true));
        $command = $application->find('debug:asset-map');
        $tester = new CommandTester($command);
        $res = $tester->execute(['name' => 'stimulus']);

        $this->assertSame(0, $res);
        $this->assertStringContainsString('stimulus', $tester->getDisplay());
        $this->assertStringNotContainsString('lodash', $tester->getDisplay());

        $res = $tester->execute(['name' => 'lodash']);
        $this->assertSame(0, $res);
        $this->assertStringNotContainsString('stimulus', $tester->getDisplay());
        $this->assertStringContainsString('lodash', $tester->getDisplay());
    }

    public function testCommandFiltersExtension()
    {
        $application = new Application(new AssetMapperTestAppKernel('test', true));
        $command = $application->find('debug:asset-map');
        $tester = new CommandTester($command);
        $res = $tester->execute(['--ext' => 'css']);

        $this->assertSame(0, $res);
        $this->assertStringNotContainsString('.js', $tester->getDisplay());

        $this->assertStringContainsString('file1.css', $tester->getDisplay());
        $this->assertStringContainsString('file3.css', $tester->getDisplay());
    }

    public function testCommandFiltersVendor()
    {
        $application = new Application(new AssetMapperTestAppKernel('test', true));
        $command = $application->find('debug:asset-map');

        $tester = new CommandTester($command);
        $res = $tester->execute(['--vendor' => true]);

        $this->assertSame(0, $res);
        $this->assertStringContainsString('vendor/lodash/', $tester->getDisplay());
        $this->assertStringContainsString('@hotwired/stimulus', $tester->getDisplay());
        $this->assertStringNotContainsString('dir2'.\DIRECTORY_SEPARATOR, $tester->getDisplay());

        $tester = new CommandTester($command);
        $res = $tester->execute(['--no-vendor' => true]);

        $this->assertSame(0, $res);
        $this->assertStringNotContainsString('vendor/lodash/', $tester->getDisplay());
        $this->assertStringNotContainsString('@hotwired/stimulus', $tester->getDisplay());
        $this->assertStringContainsString('dir2'.\DIRECTORY_SEPARATOR, $tester->getDisplay());
    }
}
