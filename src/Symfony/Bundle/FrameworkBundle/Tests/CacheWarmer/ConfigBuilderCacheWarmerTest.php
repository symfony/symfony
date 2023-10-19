<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\ConfigBuilderCacheWarmer;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

class ConfigBuilderCacheWarmerTest extends TestCase
{
    private $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/'.uniqid();
        $fs = new Filesystem();
        $fs->mkdir($this->varDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        $fs->remove($this->varDir);
        unset($this->varDir);
    }

    public function testBuildDirIsUsedAsConfigBuilderOutputDir()
    {
        $kernel = new class($this->varDir) extends Kernel implements CompilerPassInterface {
            private $varDir;

            public function __construct(string $varDir)
            {
                parent::__construct('test', false);

                $this->varDir = $varDir;
            }

            public function registerBundles(): iterable
            {
                yield new FrameworkBundle();
            }

            public function getBuildDir(): string
            {
                return $this->varDir.'/build';
            }

            public function getCacheDir(): string
            {
                return $this->varDir.'/cache';
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(static function (ContainerBuilder $container) {
                    $container->loadFromExtension('framework', [
                        'annotations' => false,
                        'handle_all_throwables' => true,
                        'http_method_override' => false,
                        'php_errors' => ['log' => true],
                    ]);
                });
            }

            public function process(ContainerBuilder $container): void
            {
                $container->removeDefinition('config_builder.warmer');
            }
        };
        $kernel->boot();

        self::assertDirectoryDoesNotExist($kernel->getBuildDir().'/Symfony');
        self::assertDirectoryDoesNotExist($kernel->getCacheDir().'/Symfony');

        $warmer = new ConfigBuilderCacheWarmer($kernel);
        $warmer->warmUp($kernel->getCacheDir());

        self::assertDirectoryDoesNotExist($kernel->getBuildDir().'/Symfony');
        self::assertDirectoryDoesNotExist($kernel->getCacheDir().'/Symfony');

        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertDirectoryExists($kernel->getBuildDir().'/Symfony');
        self::assertDirectoryDoesNotExist($kernel->getCacheDir().'/Symfony');
    }
}
