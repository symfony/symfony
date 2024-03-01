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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class AssetMapperRequireCommandTest extends TestCase
{
    private AssetMapperTestAppKernel $kernel;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->kernel = new AssetMapperTestAppKernel('test', true);

        $this->filesystem->rename($this->kernel->getProjectDir().'/importmap.php', $this->kernel->getProjectDir().'/importmap.php.bak');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->kernel->getProjectDir().'/importmap.php');
        $this->filesystem->rename($this->kernel->getProjectDir().'/importmap.php.bak', $this->kernel->getProjectDir().'/importmap.php');
    }

    public function testDefaultRequireCommand()
    {
        $this->kernel->boot();
        $application = new Application($this->kernel);
        $command = $application->find('importmap:require');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'packages' => ['lodash'],
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('lodash', $output);
        $commandTester->assertCommandIsSuccessful();
    }

    public function testRequireWithInvalidResolverCommand()
    {
        $this->kernel->boot();
        $application = new Application($this->kernel);
        $command = $application->find('importmap:require');
        $commandTester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The resolver "invalid" does not exist.');

        $commandTester->execute([
            'packages' => ['lodash'],
            '--resolver' => 'invalid',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
    }
}
