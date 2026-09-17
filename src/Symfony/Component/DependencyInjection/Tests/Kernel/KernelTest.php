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
    }
}

class BrokenKernel extends TestKernel
{
    protected function build(ContainerBuilder $container): void
    {
        throw new \LogicException('Broken build.');
    }
}
