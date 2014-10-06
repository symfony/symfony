<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand;

use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class CacheClearCommandTest extends TestCase
{
    /** @var TestAppKernel */
    protected $kernel;
    /** @var Filesystem */
    protected $fs;
    private $rootDir;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->kernel = new TestAppKernel('test', true);
        $this->rootDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sf2_cache_');
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
        foreach ($metaFiles as $file) {
            $configCache = new ConfigCache(substr($file, 0, -5), true);
            $this->assertTrue(
                $configCache->isFresh(),
                sprintf(
                    'Meta file "%s" is not fresh',
                    (string) $file
                )
            );
        }
    }
}
