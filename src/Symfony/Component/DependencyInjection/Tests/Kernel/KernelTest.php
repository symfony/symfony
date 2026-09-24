<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;

class KernelTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/sf_di_kernel_'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->projectDir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->projectDir);
    }

    public function testBuildContainerWritesCachedirTag()
    {
        $kernel = new TestKernel($this->projectDir);
        $kernel->boot();

        foreach ([$kernel->getCacheDir(), $kernel->getBuildDir()] as $dir) {
            $cachedirTag = $dir.'/CACHEDIR.TAG';
            $this->assertFileExists($cachedirTag);
            $this->assertStringStartsWith('Signature: 8a477f597d28d172789f06886806bc55', file_get_contents($cachedirTag));
        }
    }

    public function testBuildContainerWritesCompilerLogWithoutDebug()
    {
        $kernel = new TestKernel($this->projectDir);
        $kernel->boot();

        $this->assertFalse($kernel->isDebug());

        $container = $kernel->getContainer();
        $compilerLog = $container->getParameter('kernel.build_dir').'/'.$container->getParameter('kernel.container_class').'Compiler.log';

        $this->assertFileExists($compilerLog);
        $this->assertStringContainsString('Removed service "unused_service"; reason: unused.', file_get_contents($compilerLog));
    }

    public function testBuildContainerDoesNotWriteCompilerLogWhenBuildingFails()
    {
        $kernel = new BrokenKernel($this->projectDir);

        try {
            $kernel->boot();
            $this->fail('Booting the kernel should have failed.');
        } catch (\LogicException $e) {
            $this->assertSame('Broken build.', $e->getMessage());
        }

        $this->assertEmpty(glob($kernel->getBuildDir().'/*Compiler.log'));
    }

    public function testDumpContainerWritesContainerDirectory()
    {
        $kernel = new TestKernel($this->projectDir);
        $kernel->boot();

        $containerDirs = glob($kernel->getBuildDir().'/*', \GLOB_ONLYDIR);

        $this->assertCount(1, $containerDirs);
        $this->assertSame(strstr($kernel->getContainer()::class, '\\', true), basename($containerDirs[0]));
        $this->assertFileExists($containerDirs[0].'/'.$kernel->getContainer()->getParameter('kernel.container_class').'.php');

        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->assertSame(0o777 & ~umask(), fileperms($containerDirs[0]) & 0o777);

            foreach (glob($containerDirs[0].'/*') as $file) {
                $this->assertSame(0o666 & ~umask(), fileperms($file) & 0o777);
            }
        }
    }

    public function testDumpContainerReusesExistingContainerDirectory()
    {
        $kernel = new TestKernel($this->projectDir);
        $kernel->boot();

        $buildDir = $kernel->getBuildDir();
        $class = $kernel->getContainer()->getParameter('kernel.container_class');
        [$containerDir] = glob($buildDir.'/Container*', \GLOB_ONLYDIR);
        $mtime = time() - 3600;
        foreach (glob($containerDir.'/*') as $file) {
            touch($file, $mtime);
        }
        unlink($buildDir.'/'.$class.'.php');

        (new TestKernel($this->projectDir))->boot();

        $this->assertSame([$containerDir], glob($buildDir.'/*', \GLOB_ONLYDIR));

        clearstatcache();
        $this->assertGreaterThan($mtime, filemtime($containerDir.'/'.$class.'.php'));
        $this->assertSame($mtime, filemtime($containerDir.'/getPublicServiceService.php'));
    }

    public function testDumpContainerRestoresMissingFilesInExistingContainerDirectory()
    {
        $kernel = new TestKernel($this->projectDir);
        $kernel->boot();

        $buildDir = $kernel->getBuildDir();
        $class = $kernel->getContainer()->getParameter('kernel.container_class');
        [$containerDir] = glob($buildDir.'/Container*', \GLOB_ONLYDIR);
        $code = file_get_contents($containerDir.'/'.$class.'.php');
        unlink($containerDir.'/'.$class.'.php');
        unlink($buildDir.'/'.$class.'.php');

        (new TestKernel($this->projectDir))->boot();

        $this->assertStringEqualsFile($containerDir.'/'.$class.'.php', $code);
    }
}

class TestKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(private string $projectDir)
    {
        parent::__construct('test', false);
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getBuildDir(): string
    {
        return $this->projectDir.'/var/build';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register('unused_service', \stdClass::class);
        $container->register('public_service', \stdClass::class)->setPublic(true);
    }
}

class BrokenKernel extends TestKernel
{
    protected function build(ContainerBuilder $container): void
    {
        throw new \LogicException('Broken build.');
    }
}
