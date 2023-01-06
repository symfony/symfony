<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\AboutCommand;

use Symfony\Bundle\FrameworkBundle\Command\AboutCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Command\AboutCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class AboutCommandTest extends TestCase
{
    /** @var Filesystem */
    private $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
    }

    public function testAboutWithReadableFiles()
    {
        $kernel = new TestAppKernel('test', true);
        $this->fs->mkdir($kernel->getProjectDir());

        $this->fs->dumpFile($kernel->getCacheDir().'/readable_file', 'The file content.');
        $this->fs->chmod($kernel->getCacheDir().'/readable_file', 0777);

        $tester = $this->createCommandTester($kernel);
        $ret = $tester->execute([]);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('Cache directory', $tester->getDisplay());
        $this->assertStringContainsString('Log directory', $tester->getDisplay());

        $this->fs->chmod($kernel->getCacheDir().'/readable_file', 0777);

        try {
            $this->fs->remove($kernel->getProjectDir());
        } catch (IOException $e) {
        }
    }

    public function testAboutWithUnreadableFiles()
    {
        $kernel = new TestAppKernel('test', true);
        $this->fs->mkdir($kernel->getProjectDir());

        // skip test on Windows; PHP can't easily set file as unreadable on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot run on Windows.');
        }

        $this->fs->dumpFile($kernel->getCacheDir().'/unreadable_file', 'The file content.');
        $this->fs->chmod($kernel->getCacheDir().'/unreadable_file', 0222);

        $tester = $this->createCommandTester($kernel);
        $ret = $tester->execute([]);

        $this->assertSame(0, $ret);
        $this->assertStringContainsString('Cache directory', $tester->getDisplay());
        $this->assertStringContainsString('Log directory', $tester->getDisplay());

        $this->fs->chmod($kernel->getCacheDir().'/unreadable_file', 0777);

        try {
            $this->fs->remove($kernel->getProjectDir());
        } catch (IOException $e) {
        }
    }

    private function createCommandTester(TestAppKernel $kernel): CommandTester
    {
        $application = new Application($kernel);
        $application->add(new AboutCommand());

        return new CommandTester($application->find('about'));
    }
}
