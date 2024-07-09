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
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;

class ConfigBuilderCacheWarmerTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $fs = new Filesystem();
        $this->varDir = tempnam(sys_get_temp_dir(), 'sf_var_');
        $fs->remove($this->varDir);
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
        $kernel = new TestKernel($this->varDir);
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

    public function testWithCustomKernelImplementation()
    {
        $kernel = new class($this->varDir) implements KernelInterface {
            private $varDir;

            public function __construct(string $varDir)
            {
                $this->varDir = $varDir;
            }

            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }

            public function registerBundles(): iterable
            {
                yield new FrameworkBundle();
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
            }

            public function boot(): void
            {
            }

            public function shutdown(): void
            {
            }

            public function getBundles(): array
            {
                $bundles = [];
                foreach ($this->registerBundles() as $bundle) {
                    $bundles[$bundle->getName()] = $bundle;
                }

                return $bundles;
            }

            public function getBundle(string $name): BundleInterface
            {
                foreach ($this->getBundles() as $bundleName => $bundle) {
                    if ($bundleName === $name) {
                        return $bundle;
                    }
                }

                throw new \InvalidArgumentException();
            }

            public function locateResource(string $name): string
            {
                return __DIR__;
            }

            public function getEnvironment(): string
            {
                return 'test';
            }

            public function isDebug(): bool
            {
                return false;
            }

            public function getProjectDir(): string
            {
                return __DIR__;
            }

            public function getContainer(): ContainerInterface
            {
                $container = new ContainerBuilder();
                $container->setParameter('kernel.debug', $this->isDebug());

                return $container;
            }

            public function getStartTime(): float
            {
                return -\INF;
            }

            public function getBuildDir(): string
            {
                return $this->varDir.'/build';
            }

            public function getCacheDir(): string
            {
                return $this->varDir.'/cache';
            }

            public function getLogDir(): string
            {
                return $this->varDir.'/log';
            }

            public function getCharset(): string
            {
                return 'UTF-8';
            }
        };
        $kernel->boot();

        $warmer = new ConfigBuilderCacheWarmer($kernel);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/FrameworkConfig.php');
    }

    public function testExtensionAddedInKernel()
    {
        $kernel = new class($this->varDir) extends TestKernel {
            protected function build(ContainerBuilder $container): void
            {
                $container->registerExtension(new class() extends Extension implements ConfigurationInterface {
                    public function load(array $configs, ContainerBuilder $container): void
                    {
                    }

                    public function getConfigTreeBuilder(): TreeBuilder
                    {
                        $treeBuilder = new TreeBuilder('app');
                        $rootNode = $treeBuilder->getRootNode();

                        $rootNode
                            ->children()
                                ->scalarNode('provider')->end()
                            ->end()
                        ;

                        return $treeBuilder;
                    }

                    public function getAlias(): string
                    {
                        return 'app';
                    }
                });
            }
        };
        $kernel->boot();

        $warmer = new ConfigBuilderCacheWarmer($kernel);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/FrameworkConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/AppConfig.php');
    }

    public function testKernelAsExtension()
    {
        $kernel = new class($this->varDir) extends TestKernel implements ExtensionInterface, ConfigurationInterface {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getXsdValidationBasePath(): string|false
            {
                return false;
            }

            public function getNamespace(): string
            {
                return 'http://www.example.com/schema/acme';
            }

            public function getAlias(): string
            {
                return 'kernel';
            }

            public function getConfigTreeBuilder(): TreeBuilder
            {
                $treeBuilder = new TreeBuilder('kernel');
                $rootNode = $treeBuilder->getRootNode();

                $rootNode
                    ->children()
                        ->scalarNode('provider')->end()
                    ->end()
                ;

                return $treeBuilder;
            }
        };
        $kernel->boot();

        $warmer = new ConfigBuilderCacheWarmer($kernel);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/FrameworkConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/KernelConfig.php');
    }

    public function testExtensionsExtendedInBuildMethods()
    {
        $kernel = new class($this->varDir) extends TestKernel {
            protected function build(ContainerBuilder $container): void
            {
                /** @var TestSecurityExtension $extension */
                $extension = $container->getExtension('test_security');
                $extension->addAuthenticatorFactory(new class() implements TestAuthenticatorFactoryInterface {
                    public function getKey(): string
                    {
                        return 'token';
                    }

                    public function addConfiguration(NodeDefinition $node): void
                    {
                    }
                });
            }

            public function registerBundles(): iterable
            {
                yield from parent::registerBundles();

                yield new class() extends Bundle {
                    public function getContainerExtension(): ExtensionInterface
                    {
                        return new TestSecurityExtension();
                    }
                };

                yield new class() extends Bundle {
                    public function build(ContainerBuilder $container): void
                    {
                        /** @var TestSecurityExtension $extension */
                        $extension = $container->getExtension('test_security');
                        $extension->addAuthenticatorFactory(new class() implements TestAuthenticatorFactoryInterface {
                            public function getKey(): string
                            {
                                return 'form-login';
                            }

                            public function addConfiguration(NodeDefinition $node): void
                            {
                                $node
                                    ->children()
                                        ->scalarNode('provider')->end()
                                    ->end()
                                ;
                            }
                        });
                    }
                };
            }
        };
        $kernel->boot();

        $warmer = new ConfigBuilderCacheWarmer($kernel);
        $warmer->warmUp($kernel->getCacheDir(), $kernel->getBuildDir());

        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/FrameworkConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/SecurityConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/Security/FirewallConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/Security/FirewallConfig/FormLoginConfig.php');
        self::assertFileExists($kernel->getBuildDir().'/Symfony/Config/Security/FirewallConfig/TokenConfig.php');
    }
}

class TestKernel extends Kernel implements CompilerPassInterface
{
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
}

interface TestAuthenticatorFactoryInterface
{
    public function getKey(): string;

    public function addConfiguration(NodeDefinition $builder): void;
}

class TestSecurityExtension extends Extension implements ConfigurationInterface
{
    /** @var TestAuthenticatorFactoryInterface[] */
    private $factories;

    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return $this;
    }

    public function addAuthenticatorFactory(TestAuthenticatorFactoryInterface $factory): void
    {
        $this->factories[] = $factory;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('security');
        $rootNode = $treeBuilder->getRootNode();

        $firewallNodeBuilder = $rootNode
            ->fixXmlConfig('firewall')
            ->children()
                ->arrayNode('firewalls')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
        ;

        foreach ($this->factories as $factory) {
            $name = str_replace('-', '_', $factory->getKey());
            $factoryNode = $firewallNodeBuilder->arrayNode($name);

            $factory->addConfiguration($factoryNode);
        }

        return $treeBuilder;
    }
}
