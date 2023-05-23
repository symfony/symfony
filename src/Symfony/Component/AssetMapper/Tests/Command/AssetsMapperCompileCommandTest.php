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
use Symfony\Component\AssetMapper\Tests\fixtures\AssetMapperTestAppKernel;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class AssetsMapperCompileCommandTest extends TestCase
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
        // put old "built" versions to make sure the system skips using these
        $this->filesystem->mkdir($targetBuildDir);
        file_put_contents($targetBuildDir.'/manifest.json', '{}');
        file_put_contents($targetBuildDir.'/importmap.json', '{"imports": {}}');
        file_put_contents($targetBuildDir.'/importmap.preload.json', '{}');

        $command = $application->find('asset-map:compile');
        $tester = new CommandTester($command);
        $res = $tester->execute([]);
        $this->assertSame(0, $res);
        // match Compiling \d+ assets
        $this->assertMatchesRegularExpression('/Compiled \d+ assets/', $tester->getDisplay());

        $this->assertFileExists($targetBuildDir.'/subdir/file5-f4fdc37375c7f5f2629c5659a0579967.js');
        $this->assertSame(<<<EOF
        import '../file4.js';
        console.log('file5.js');

        EOF, file_get_contents($targetBuildDir.'/subdir/file5-f4fdc37375c7f5f2629c5659a0579967.js'));

        $finder = new Finder();
        $finder->in($targetBuildDir)->files();
        $this->assertCount(10, $finder);
        $this->assertFileExists($targetBuildDir.'/manifest.json');

        $this->assertSame([
            'already-abcdefVWXYZ0123456789.digested.css',
            'file1.css',
            'file2.js',
            'file3.css',
            'file4.js',
            'subdir/file5.js',
            'subdir/file6.js',
        ], array_keys(json_decode(file_get_contents($targetBuildDir.'/manifest.json'), true)));

        $this->assertFileExists($targetBuildDir.'/importmap.json');
        $actualImportMap = json_decode(file_get_contents($targetBuildDir.'/importmap.json'), true);
        $this->assertSame([
            '@hotwired/stimulus',
            'lodash',
            'file6',
            '/assets/subdir/file5.js', // imported by file6
            '/assets/file4.js', // imported by file5
        ], array_keys($actualImportMap['imports']));

        $this->assertFileExists($targetBuildDir.'/importmap.preload.json');
        $actualPreload = json_decode(file_get_contents($targetBuildDir.'/importmap.preload.json'), true);
        $this->assertCount(4, $actualPreload);
        $this->assertStringStartsWith('https://unpkg.com/@hotwired/stimulus', $actualPreload[0]);
        $this->assertStringStartsWith('/assets/subdir/file6-', $actualPreload[1]);
        $this->assertStringStartsWith('/assets/subdir/file5-', $actualPreload[2]);
        $this->assertStringStartsWith('/assets/file4-', $actualPreload[3]);
    }
}
