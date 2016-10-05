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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class CacheClearCommandTest extends TestCase
{
    /** @var TestAppKernel */
    private $kernel;
    /** @var Filesystem */
    private $fs;
    private $rootDir;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->kernel = new TestAppKernel('test', true);
        $this->rootDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sf2_cache_', true);
        $this->kernel->setRootDir($this->rootDir);
        $this->fs->mkdir($this->rootDir);
    }

    protected function tearDown()
    {
        $this->fs->remove($this->rootDir);
    }

    public function testCacheIsFreshAfterCacheClearedWithWarmup()
    {
        $input = new ArrayInput(array('cache:clear'));
        $application = new Application($this->kernel);
        $application->setCatchExceptions(false);

        $application->doRun($input, new NullOutput());

        // Ensure that all *.meta files are fresh
        $finder = new Finder();
        $metaFiles = $finder->files()->in($this->kernel->getCacheDir())->name('*.php.meta');
        // simply check that cache is warmed up
        $this->assertGreaterThanOrEqual(1, count($metaFiles));
        $configCacheFactory = new ConfigCacheFactory(true);
        $that = $this;

        foreach ($metaFiles as $file) {
            $configCacheFactory->cache(substr($file, 0, -5), function () use ($that, $file) {
                $that->fail(sprintf('Meta file "%s" is not fresh', (string) $file));
            });
        }

        // check that app kernel file present in meta file of container's cache
        $containerRef = new \ReflectionObject($this->kernel->getContainer());
        $containerFile = $containerRef->getFileName();
        $containerMetaFile = $containerFile.'.meta';
        $kernelRef = new \ReflectionObject($this->kernel);
        $kernelFile = $kernelRef->getFileName();
        /** @var ResourceInterface[] $meta */
        $meta = unserialize(file_get_contents($containerMetaFile));
        $found = false;
        foreach ($meta as $resource) {
            if ((string) $resource === $kernelFile) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Kernel file should present as resource');
        $this->assertRegExp(sprintf('/\'kernel.container_class\'\s*=>\s*\'%s\'/', get_class($this->kernel->getContainer())), file_get_contents($containerFile), 'kernel.container_class is properly set on the dumped container');
    }
}
