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
use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;
use Symfony\Component\AssetMapper\Tests\Fixtures\AssetMapperTestAppKernel;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class AssetMapperCompileCommandTest extends TestCase
{
    private AssetMapperTestAppKernel $kernel;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->kernel = new AssetMapperTestAppKernel('test', true);
        $this->filesystem->mkdir($this->kernel->getProjectDir().'/public');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->kernel->getProjectDir().'/public');
        $this->filesystem->remove($this->kernel->getProjectDir().'/var');
    }

    public function testAssetsAreCompiled()
    {
        $application = new Application($this->kernel);

        $targetBuildDir = $this->kernel->getProjectDir().'/public/assets';
        if (is_dir($targetBuildDir)) {
            $this->filesystem->remove($targetBuildDir);
        }
        // put old "built" versions to make sure the system skips using these
        $this->filesystem->dumpFile($targetBuildDir.'/manifest.json', '{}');
        $this->filesystem->dumpFile($targetBuildDir.'/importmap.json', '{}');
        $this->filesystem->dumpFile($targetBuildDir.'/entrypoint.file6.json', '[]');

        $command = $application->find('asset-map:compile');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        $this->assertSame(0, $exitCode);
        // match Compiling \d+ assets
        $this->assertMatchesRegularExpression('/Compiled \d+ assets/', $tester->getDisplay());

        $this->assertFileExists($targetBuildDir.'/subdir/file5-9P3Dc3X.js');
        $this->assertSame(<<<EOF
        import '../file4.js';
        console.log('file5.js');

        EOF, $this->filesystem->readFile($targetBuildDir.'/subdir/file5-9P3Dc3X.js'));

        $finder = new Finder();
        $finder->in($targetBuildDir)->files();
        $this->assertCount(13, $finder); // 10 files + manifest.json & importmap.json + entrypoint.file6.json
        $this->assertFileExists($targetBuildDir.'/manifest.json');

        $this->assertSame([
            'already-abcdefVWXYZ0123456789.digested.css',
            'file1.css',
            'file2.js',
            'file3.css',
            'file4.js',
            'subdir/file5.js',
            'subdir/file6.js',
            'vendor/@hotwired/stimulus/stimulus.index.js',
            'vendor/lodash/lodash.index.js',
            'voilà.css',
        ], array_keys(json_decode($this->filesystem->readFile($targetBuildDir.'/manifest.json'), true)));

        $this->assertFileExists($targetBuildDir.'/importmap.json');
        $actualImportMap = json_decode($this->filesystem->readFile($targetBuildDir.'/importmap.json'), true);
        $this->assertSame([
            '@hotwired/stimulus', // in importmap
            'lodash', // in importmap
            'file6',  // in importmap
            '/assets/subdir/file5.js', // imported by file6
            '/assets/file4.js', // imported by file5
            'file2', // in importmap
            '/assets/file1.css', // imported by file2.js
            'file3.css', // in importmap
            // imported by file3.css: CSS imported by CSS does not need to be in the importmap
            // 'already-abcdefVWXYZ0123456789.digested.css',
        ], array_keys($actualImportMap));
        $this->assertSame('js', $actualImportMap['@hotwired/stimulus']['type']);

        $this->assertFileExists($targetBuildDir.'/entrypoint.file6.json');
        $entrypointData = json_decode($this->filesystem->readFile($targetBuildDir.'/entrypoint.file6.json'), true);
        $this->assertSame([
            '/assets/subdir/file5.js',
            '/assets/file4.js',
        ], $entrypointData);
    }

    public function testEventIsDispatched()
    {
        $this->kernel->boot();
        $application = new Application($this->kernel);
        $container = $this->kernel->getContainer();
        $dispatcher = $container->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);

        $listenerCalled = false;
        $dispatcher->addListener(PreAssetsCompileEvent::class, function (PreAssetsCompileEvent $event) use (&$listenerCalled) {
            $listenerCalled = true;
            $this->assertInstanceOf(OutputInterface::class, $event->getOutput());
        });

        $command = $application->find('asset-map:compile');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $this->assertTrue($listenerCalled);
    }
}
