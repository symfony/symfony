<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app\ConfigCacheFreshness;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\AppKernel;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FreshnessKernel extends AppKernel
{
    private int $capturedAt;

    public function __construct(private string $directory, private string $mode)
    {
        parent::__construct('unused', 'ConfigCacheFreshness', $directory.'/config/services.yml', 'test', true);
    }

    public function getCacheDir(): string
    {
        return $this->directory.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory.'/logs';
    }

    /**
     * @return array{'.kernel.config_dir': string, ...<string, mixed>}
     */
    protected function getKernelParameters(): array
    {
        return ['.kernel.config_dir' => $this->directory.'/config'] + parent::getKernelParameters();
    }

    /**
     * Wraps the production YAML parser to save its input between parsing and resource registration.
     */
    protected function getContainerLoader(ContainerInterface $container): DelegatingLoader
    {
        $loader = new class($container, new FileLocator($this), $this->environment) extends YamlFileLoader {
            public FreshnessKernel $kernel;

            /**
             * @return array{framework?: array<string, mixed>, parameters?: array<string, string>, services?: array<string, mixed>}|null
             */
            protected function loadFile(string $file): ?array
            {
                $capturedAt = $this->kernel->prepareRead($file);
                $content = parent::loadFile($file);
                $this->kernel->mutate('read', $capturedAt);

                return $content;
            }
        };
        $loader->kernel = $this;

        return new DelegatingLoader(new LoaderResolver([$loader, ...parent::getContainerLoader($container)->getResolver()->getLoaders()]));
    }

    /**
     * Registers one-shot compiler edits, build counters, and the late-reflection scenario before normal compilation.
     */
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        if ('reflection' === $this->mode && !is_file($this->directory.'/changed')) {
            require $this->directory.'/classes/Service.php';
            Mutation::change($this->directory.'/classes/Service.php');
            touch($this->directory.'/classes/Service.php', time() - 1);
            clearstatcache();
        }
        $container->addCompilerPass(new class($this) implements CompilerPassInterface {
            public function __construct(private FreshnessKernel $kernel)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $this->kernel->recordBuild();
                $this->kernel->configureScenario($container);
                $this->kernel->mutate('compiler', time());
                $this->kernel->mutate('recent', time());
            }
        });
    }

    /**
     * Installs the selected mutation and cache counters in the ordinary application services.
     */
    public function configureScenario(ContainerBuilder $container): void
    {
        $container->register('fixture.cache_factory', CountingCacheFactory::class)
            ->setDecoratedService('config_cache_factory')
            ->setArguments([new Reference('fixture.cache_factory.inner'), $this->directory]);

        if ('route-yaml' === $this->mode || 'route-checker' === $this->mode || str_starts_with($this->mode, 'route-directory')) {
            $container->getDefinition('routing.loader.yml')->setClass(MutatingRoutingYamlLoader::class);
        }
        if ('route-checker' === $this->mode) {
            $container->addResource(new FileResource($this->directory.'/config/routes.yml'));
        }
        if ('route-directory-equal' === $this->mode) {
            $container->getDefinition('routing.loader.directory')->setClass(EqualTimestampDirectoryLoader::class);
        }
        if ('reflection-registered' === $this->mode && !is_file($this->directory.'/changed')) {
            $container->getReflectionClass('FreshnessFixture\\Service');
            Mutation::change($this->directory.'/classes/Service.php');
            touch($this->directory.'/classes/Service.php', time() - 1);
            clearstatcache();
        }
        if (str_starts_with($this->mode, 'translation-') && 'translation-discovery' !== $this->mode) {
            $format = str_starts_with($this->mode, 'translation-directory') ? 'yml' : substr($this->mode, \strlen('translation-'));
            $id = 'translation.loader.'.$format;
            if (str_starts_with($this->mode, 'translation-directory')) {
                $original = $container->getDefinition($id);
                $container->setDefinition($id.'.original', $original);
                $container->setDefinition($id, (new Definition(MutatingTranslationLoader::class, [new Reference($id.'.original')]))->setTags($original->getTags()));
                $original->clearTags();
            } else {
                $class = match ($format) {
                    'yml' => MutatingTranslationYamlLoader::class,
                    'dat' => MutatingTranslationIcuDatLoader::class,
                    'res' => MutatingTranslationIcuResLoader::class,
                    default => null,
                };
                if (null !== $class) {
                    $container->getDefinition($id)->setClass($class);
                }
                $resource = $this->directory.'/inputs/'.match ($format) {
                    'dat' => 'resources',
                    'res' => 'res',
                    'xliff' => 'probe.xlf',
                    'qt' => 'probe.ts',
                    default => 'probe.yaml',
                };
                if ('xliff' === $format || 'qt' === $format) {
                    $resource = 'translationcapture://'.$resource;
                }
                $container->getDefinition('translator.default')->addMethodCall('addResource', ['qt' === $format ? 'ts' : $format, $resource, 'en', 'probe']);
            }
        }
        if ('asset' === $this->mode) {
            $container->setAlias('fixture.asset_mapper', 'asset_mapper')->setPublic(true);
        }
        if ('glob' === $this->mode && !is_file($this->directory.'/changed')) {
            file_put_contents($this->directory.'/classes/Added.php', '<?php namespace FreshnessFixture; class Added { public string $value = "v2"; }');
            file_put_contents($this->directory.'/changed', 'glob');
        }
        if ('translation-discovery' === $this->mode && !is_file($this->directory.'/changed')) {
            file_put_contents($this->directory.'/translations/probe.fr.yaml', 'key: v2');
            file_put_contents($this->directory.'/changed', 'translation');
        }
    }

    /**
     * Counts container compilations in a scenario file outside the tracked inputs.
     */
    public function recordBuild(): void
    {
        $path = $this->directory.'/builds';
        file_put_contents($path, (is_file($path) ? (int) file_get_contents($path) : 0) + 1);
    }

    /**
     * Keeps the recent-input scenario inside the parser's capture window and records that boundary.
     */
    public function prepareRead(string $file): int
    {
        $this->capturedAt = time();
        if ('recent' === $this->mode && !is_file($this->directory.'/mutation.json') && $file === $this->directory.'/config/services.yml') {
            touch($file, $this->capturedAt - 1);
            clearstatcache(true, $file);
        }

        return $this->capturedAt;
    }

    /**
     * Saves configuration once at the selected loading phase and records the modeled timestamp ordering.
     */
    public function mutate(string $mode, int $capturedAt): void
    {
        if ($mode !== $this->mode || is_file($this->directory.'/mutation.json')) {
            return;
        }

        $path = $this->directory.'/config/services.yml';
        $capturedAt = 'recent' === $mode ? $this->capturedAt : $capturedAt;
        $before = filemtime($path);
        file_put_contents($path, str_replace('v1', 'v2', file_get_contents($path)));
        $after = 'recent' === $mode ? $before : time() - 1;
        touch($path, $after);
        clearstatcache(true, $path);
        file_put_contents($this->directory.'/mutation.json', json_encode(['before' => $before, 'after' => $after, 'captured_at' => $capturedAt], \JSON_THROW_ON_ERROR));
    }
}
