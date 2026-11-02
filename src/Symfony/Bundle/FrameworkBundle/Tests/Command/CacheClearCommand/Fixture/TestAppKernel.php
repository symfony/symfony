<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestAppKernel extends Kernel
{
    private static ?string $projectDir = null;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return self::$projectDir ??= sys_get_temp_dir().'/sf_cache_clear_command_'.bin2hex(random_bytes(4));
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.\DIRECTORY_SEPARATOR.'config.yml');
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register('logger', NullLogger::class);
        $container->register(DummyFileCacheWarmer::class)
            ->addTag('kernel.cache_warmer');
        $container->register(TerminateListener::class)
            ->addTag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'onConsoleTerminate']);
    }
}

class DummyFileCacheWarmer implements CacheWarmerInterface
{
    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        file_put_contents($cacheDir.'/dummy.txt', 'Hello');

        return [];
    }
}

class TerminateListener
{
    public static int $calls = 0;

    public function onConsoleTerminate(): void
    {
        ++self::$calls;
    }
}
