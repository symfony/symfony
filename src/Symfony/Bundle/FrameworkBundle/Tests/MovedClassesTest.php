<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader as LegacyAttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouterBundle;

class MovedClassesTest extends TestCase
{
    #[DataProvider('provideMovedClasses')]
    #[RunInSeparateProcess]
    public function testMovedClassesSatisfyLegacyTypes(string $legacyClass, string $class)
    {
        $this->assertFalse(class_exists($legacyClass, false));
        $this->assertTrue(is_a($class, $legacyClass, true));
    }

    #[DataProvider('provideMovedClasses')]
    #[Group('legacy')]
    #[IgnoreDeprecations]
    #[RunInSeparateProcess]
    public function testLoadingTheLegacyNameFirstTriggersADeprecation(string $legacyClass, string $class)
    {
        $this->assertFalse(class_exists($legacyClass, false));
        $this->assertFalse(class_exists($class, false));
        $replacement = match ($legacyClass) {
            'Symfony\Bundle\FrameworkBundle\Controller\RedirectController' => 'the "redirect_to_route" or "redirect_to" routing option',
            default => '"'.$class.'"',
        };
        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.2: The "%s" class is deprecated, use %s instead.', $legacyClass, $replacement));

        $this->assertTrue(class_exists($legacyClass));
        $this->assertSame($class, (new \ReflectionClass($legacyClass))->getName());
    }

    #[RunInSeparateProcess]
    public function testTheMovedLoaderCanBeInjectedFromADumpedContainer()
    {
        $services = new ContainerBuilder();
        $loader = new PhpFileLoader($services, new FileLocator(\dirname((new \ReflectionClass(RouterBundle::class))->getFileName()).'/Resources/config'));
        $loader->load('routing.php');

        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setDefinition('routing.loader.attribute', clone $services->getDefinition('routing.loader.attribute'));
        $container->register(LegacyAttributeLoaderConsumer::class)
            ->setArguments([new Reference('routing.loader.attribute')])
            ->setPublic(true);
        $container->compile();

        eval('?>'.(new PhpDumper($container))->dump(['class' => 'LegacyAttributeLoaderContainer']));

        $consumer = (new \LegacyAttributeLoaderContainer())->get(LegacyAttributeLoaderConsumer::class);

        $this->assertInstanceOf(AttributeRouteControllerLoader::class, $consumer->loader);
    }

    #[RunInSeparateProcess]
    public function testAnAppGetsTheMovedServicesAsInstancesOfTheirLegacyTypes()
    {
        $kernel = new MovedClassesKernel(sys_get_temp_dir().'/'.uniqid('sf_moved_classes_', true));

        try {
            $kernel->boot();
            $container = $kernel->getContainer()->get('test.service_container');

            foreach ([
                ['routing.loader.attribute', 'Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader'],
                ['router.default', 'Symfony\Bundle\FrameworkBundle\Routing\Router'],
                ['routing.loader', 'Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader'],
                ['router.cache_warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer'],
                ['console.command.router_match', 'Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand'],
                [\Symfony\Component\Routing\Controller\RedirectController::class, 'Symfony\Bundle\FrameworkBundle\Controller\RedirectController'],
                ['translator.default', 'Symfony\Bundle\FrameworkBundle\Translation\Translator'],
                ['translation.warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\TranslationsCacheWarmer'],
                ['console.command.cache_pool_delete', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand'],
                ['console.command.cache_pool_invalidate_tags', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand'],
                ['console.command.cache_pool_list', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolListCommand'],
                ['console.command.cache_pool_prune', 'Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand'],
                ['cache_pool_clearer.cache_warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\CachePoolClearerCacheWarmer'],
                ['serializer.mapping.cache_warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer'],
                ['serializer.mapping.cache_warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer'],
                ['validator.mapping.cache_warmer', 'Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer'],
            ] as [$id, $legacyClass]) {
                $service = $container->get($id);
                $this->assertTrue(is_a($service, $legacyClass), \sprintf('The "%s" service is a "%s", not a "%s".', $id, get_debug_type($service), $legacyClass));
            }
        } finally {
            (new Filesystem())->remove($kernel->getCacheDir());
        }
    }

    public static function provideMovedClasses(): iterable
    {
        yield ['Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader', AttributeRouteControllerLoader::class];
        yield ['Symfony\Bundle\FrameworkBundle\Routing\Router', \Symfony\Component\Routing\DependencyInjection\Router::class];
        yield ['Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader', \Symfony\Component\Routing\Loader\DelegatingLoader::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\RouterCacheWarmer', \Symfony\Component\Routing\CacheWarmer\RouterCacheWarmer::class];
        yield ['Symfony\Bundle\FrameworkBundle\Controller\RedirectController', \Symfony\Component\Routing\Controller\RedirectController::class];
        yield ['Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand', \Symfony\Component\Routing\Command\RouterMatchCommand::class];
        yield ['Symfony\Bundle\FrameworkBundle\Translation\Translator', \Symfony\Component\Translation\DependencyInjection\Translator::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\TranslationsCacheWarmer', \Symfony\Component\Translation\CacheWarmer\TranslationsCacheWarmer::class];
        yield ['Symfony\Bundle\FrameworkBundle\Command\CachePoolDeleteCommand', \Symfony\Component\Cache\Command\CachePoolDeleteCommand::class];
        yield ['Symfony\Bundle\FrameworkBundle\Command\CachePoolInvalidateTagsCommand', \Symfony\Component\Cache\Command\CachePoolInvalidateTagsCommand::class];
        yield ['Symfony\Bundle\FrameworkBundle\Command\CachePoolListCommand', \Symfony\Component\Cache\Command\CachePoolListCommand::class];
        yield ['Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand', \Symfony\Component\Cache\Command\CachePoolPruneCommand::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\AbstractPhpFileCacheWarmer', \Symfony\Component\Cache\CacheWarmer\AbstractPhpFileCacheWarmer::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\CachePoolClearerCacheWarmer', \Symfony\Component\Cache\CacheWarmer\CachePoolClearerCacheWarmer::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\SerializerCacheWarmer', \Symfony\Component\Serializer\CacheWarmer\SerializerCacheWarmer::class];
        yield ['Symfony\Bundle\FrameworkBundle\CacheWarmer\ValidatorCacheWarmer', \Symfony\Component\Validator\CacheWarmer\ValidatorCacheWarmer::class];
    }
}

class LegacyAttributeLoaderConsumer
{
    public function __construct(public readonly LegacyAttributeRouteControllerLoader $loader)
    {
    }
}

class MovedClassesKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $cacheDir)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }

    protected function configureContainer(ContainerConfigurator $c): void
    {
        $c->extension('framework', [
            'secret' => '$ecret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'serializer' => ['enabled' => true],
            'translator' => ['enabled' => true],
            'validation' => ['enabled' => true],
        ]);
        $c->services()->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
