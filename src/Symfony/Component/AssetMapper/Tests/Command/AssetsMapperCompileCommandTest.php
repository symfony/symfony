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

        $command = $application->find('asset-map:compile');
        $tester = new CommandTester($command);
        $res = $tester->execute([]);
        $this->assertSame(0, $res);
        // match Compiling \d+ assets
        $this->assertMatchesRegularExpression('/Compiling \d+ assets/', $tester->getDisplay());

        $targetBuildDir = $this->kernel->getProjectDir().'/public/assets';
        $this->assertFileExists($targetBuildDir.'/subdir/file5-f4fdc37375c7f5f2629c5659a0579967.js');
        $this->assertSame(<<<EOF
        import '../file4.js';
        console.log('file5.js');

        EOF, file_get_contents($targetBuildDir.'/subdir/file5-f4fdc37375c7f5f2629c5659a0579967.js'));

        $finder = new Finder();
        $finder->in($targetBuildDir)->files();
        $this->assertCount(9, $finder);
        $this->assertFileExists($targetBuildDir.'/manifest.json');

        $expected = [
            'file1.css',
            'file2.js',
            'file3.css',
            'subdir/file6.js',
            'subdir/file5.js',
            'file4.js',
            'already-abcdefVWXYZ0123456789.digested.css',
        ];
        $actual = array_keys(json_decode(file_get_contents($targetBuildDir.'/manifest.json'), true));
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertFileExists($targetBuildDir.'/importmap.json');
    }
}
