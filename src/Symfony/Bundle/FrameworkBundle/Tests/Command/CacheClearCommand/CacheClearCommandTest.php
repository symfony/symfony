<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand;

use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture\TerminateListener;
use Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class CacheClearCommandTest extends TestCase
{
    private TestAppKernel $kernel;
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->kernel = new TestAppKernel('test', true);
        $this->fs->mkdir($this->kernel->getProjectDir());
    }

    protected function tearDown(): void
    {
        try {
            $this->fs->remove($this->kernel->getProjectDir());
        } catch (IOException $e) {
        }
    }

    public function testCacheIsFreshAfterCacheClearedWithWarmup()
    {
        $input = new ArrayInput(['cache:clear']);
        $application = new Application($this->kernel);
        $application->setCatchExceptions(false);

        $application->doRun($input, new NullOutput());

        // Ensure that all *.meta files are fresh
        $finder = new Finder();
        $metaFiles = $finder->files()->in($this->kernel->getCacheDir())->name('*.php.meta');
        // check that cache is warmed up
        $this->assertNotEmpty($metaFiles);
        $configCacheFactory = new ConfigCacheFactory(true);

        foreach ($metaFiles as $file) {
            $configCacheFactory->cache(
                substr($file, 0, -5),
                function () use ($file) {
                    $this->fail(\sprintf('Meta file "%s" is not fresh', (string) $file));
                }
            );
        }

        // check that app kernel file present in meta file of container's cache
        $containerClass = $this->kernel->getContainer()->getParameter('kernel.container_class');
        $containerRef = new \ReflectionClass($containerClass);
        $containerFile = \dirname($containerRef->getFileName(), 2).'/'.$containerClass.'.php';
        $containerMetaFile = $containerFile.'.meta';
        $kernelRef = new \ReflectionObject($this->kernel);
        $kernelFile = $kernelRef->getFileName();
        /** @var ResourceInterface[] $meta */
        $meta = unserialize($this->fs->readFile($containerMetaFile));
        $found = false;
        foreach ($meta as $resource) {
            if ((string) $resource === $kernelFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Kernel file should present as resource');

        $containerRef = new \ReflectionClass(require $containerFile);
        $containerFile = str_replace(
            'tes_'.\DIRECTORY_SEPARATOR,
            'test'.\DIRECTORY_SEPARATOR,
            $containerRef->getFileName()
        );
        $this->assertMatchesRegularExpression(
            \sprintf('/\'kernel.container_class\'\s*=>\s*\'%s\'/', $containerClass),
            $this->fs->readFile($containerFile),
            'kernel.container_class is properly set on the dumped container'
        );
    }

    public function testConsoleTerminateListenersAreCalled()
    {
        TerminateListener::$calls = 0;

        $input = new ArrayInput(['cache:clear']);
        $application = new Application($this->kernel);
        $application->setCatchExceptions(false);

        $application->doRun($input, new NullOutput());

        $this->assertSame(1, TerminateListener::$calls);
    }

    public function testCacheIsWarmedWhenCalledTwice()
    {
        $input = new ArrayInput(['cache:clear']);
        $application = new Application(clone $this->kernel);
        $application->setCatchExceptions(false);
        $application->doRun($input, new NullOutput());

        $_SERVER['REQUEST_TIME'] = time() + 1;
        $application = new Application(clone $this->kernel);
        $application->setCatchExceptions(false);
        $application->doRun($input, new NullOutput());

        $this->assertTrue(is_file($this->kernel->getCacheDir().'/dummy.txt'));
    }

    public function testCacheIsClearedWhenBuildDirIsRecreatedConcurrently()
    {
        // Boot once to learn the exact build dir path the command will use.
        $this->kernel->boot();
        $realBuildDir = $this->kernel->getContainer()->getParameter('kernel.build_dir');

        // Simulate a concurrent HTTP request that reboots the kernel and recreates
        // the build directory in the small window after cache:clear has moved it
        // aside (rename to the "old" dir) but before it renames the freshly warmed-up
        // dir into place. Without handling, that final rename fails with
        // "Cannot rename because the target ... already exists.".
        $fs = new class($realBuildDir) extends Filesystem {
            public function __construct(private string $realBuildDir)
            {
            }

            public function rename(string $origin, string $target, bool $overwrite = false): void
            {
                parent::rename($origin, $target, $overwrite);

                if ($origin === $this->realBuildDir && !is_dir($this->realBuildDir)) {
                    $this->mkdir($this->realBuildDir);
                    file_put_contents($this->realBuildDir.'/concurrent.php', '<?php // rebuilt by a concurrent request');
                }
            }
        };

        $application = new Application($this->kernel);
        $application->setCatchExceptions(false);
        $command = $application->find('cache:clear');
        if ($command instanceof LazyCommand) {
            $command = $command->getCommand();
        }
        (new \ReflectionProperty(CacheClearCommand::class, 'filesystem'))->setValue($command, $fs);

        // Force the rebuild+publish path (the container looks stale) instead of the
        // "cache is fresh" shortcut, so the final rename is actually exercised.
        $requestTime = $_SERVER['REQUEST_TIME'];
        $_SERVER['REQUEST_TIME'] = time() + 1;
        try {
            $application->doRun(new ArrayInput(['cache:clear']), new NullOutput());
        } finally {
            $_SERVER['REQUEST_TIME'] = $requestTime;
        }

        // The freshly warmed-up cache wins: the command completes, the warmer output is
        // in place and the concurrent rebuild is gone instead of being merged into it.
        $this->assertTrue(is_file($this->kernel->getCacheDir().'/dummy.txt'));
        $this->assertFileDoesNotExist($realBuildDir.'/concurrent.php');
    }

    public function testCacheIsWarmedWithOldContainer()
    {
        $kernel = clone $this->kernel;

        // Hack to get a dumped working container,
        // BUT without "kernel.build_dir" parameter (like an old dumped container)
        $kernel->boot();
        $container = $kernel->getContainer();
        \Closure::bind(static function (Container $class) {
            unset($class->loadedDynamicParameters['kernel.build_dir']);
            unset($class->parameters['kernel.build_dir']);
        }, null, $container::class)($container);

        $input = new ArrayInput(['cache:clear']);
        $application = new Application($kernel);
        $application->setCatchExceptions(false);
        $application->doRun($input, new NullOutput());

        $this->expectNotToPerformAssertions();
    }
}
