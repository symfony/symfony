<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\TranslationBundle;

class TranslationBundleTest extends TestCase
{
    public function testTranslatorEnabledByDefault()
    {
        $container = $this->load([]);

        $this->assertTrue($container->hasDefinition('translator.default'));
        $this->assertSame('translator.default', (string) $container->getAlias('translator'));
    }

    public function testTheIdentityTranslatorAnswersWhenTranslationIsDisabled()
    {
        $container = $this->load(['enabled' => false]);

        $this->assertFalse($container->hasDefinition('translator.default'));
        $this->assertTrue($container->hasDefinition('translator'));
        $this->assertSame(IdentityTranslator::class, $container->getDefinition('translator')->getClass());
    }

    public function testTheLocalesAreReferencedInsteadOfResolved()
    {
        $translator = $this->load([])->getDefinition('translator.default');

        $this->assertSame('%kernel.enabled_locales%', $translator->getArgument(5));
        $this->assertSame(['%kernel.default_locale%'], $this->fallbackLocales($translator));
    }

    public function testMultipleFallbacks()
    {
        $translator = $this->load(['fallbacks' => ['en', 'fr']])->getDefinition('translator.default');

        $this->assertSame(['en', 'fr'], $this->fallbackLocales($translator));
    }

    public function testCacheDirCanBeDisabled()
    {
        $this->assertNull($this->load(['cache_dir' => null])->getDefinition('translator.default')->getArgument(4)['cache_dir']);
        $this->assertSame('%kernel.cache_dir%/translations', $this->load([])->getDefinition('translator.default')->getArgument(4)['cache_dir']);
    }

    public function testTranslationResourcesAreDiscovered()
    {
        $options = $this->load(['paths' => [__DIR__.'/Fixtures/translations']])->getDefinition('translator.default')->getArgument(4);

        $this->assertContains(__DIR__.'/Fixtures/translations/messages.en.yaml', $options['resource_files']['en']);
        $this->assertContains(__DIR__.'/Fixtures/translations', $options['scanned_directories']);
    }

    public function testAnUnknownPathIsRejected()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('"/nowhere" defined in translator.paths does not exist or is not a directory.');

        $this->load(['paths' => ['/nowhere']]);
    }

    public function testGlobals()
    {
        $calls = $this->load(['globals' => [
            '%%app_name%%' => 'My application',
            '{app_version}' => '1.2.3',
            '{url}' => ['message' => 'url', 'parameters' => ['scheme' => 'https://'], 'domain' => 'global'],
        ]])->getDefinition('translator.default')->getMethodCalls();
        $calls = array_values(array_filter($calls, static fn ($call) => 'addGlobalParameter' === $call[0]));

        $this->assertSame(['addGlobalParameter', ['%%app_name%%', 'My application']], $calls[0]);
        $this->assertSame(['addGlobalParameter', ['{app_version}', '1.2.3']], $calls[1]);
        $this->assertEquals(['addGlobalParameter', ['{url}', new Definition(TranslatableMessage::class, ['url', ['scheme' => 'https://'], 'global'])]], $calls[2]);
    }

    public function testWithoutGlobals()
    {
        $calls = array_column($this->load(['globals' => []])->getDefinition('translator.default')->getMethodCalls(), 0);

        $this->assertNotContains('addGlobalParameter', $calls);
    }

    public function testPseudoLocalization()
    {
        $this->assertFalse($this->load([])->hasDefinition('translator.pseudo'));

        $definition = $this->load(['pseudo_localization' => ['enabled' => true, 'accents' => false]])->getDefinition('translator.pseudo');

        $this->assertSame('translator', $definition->getDecoratedService()[0]);
        $this->assertFalse($definition->getArgument(1)['accents']);
    }

    public function testProviderLocalesAreMergedWithTheEnabledOnes()
    {
        $container = $this->load(['providers' => [
            'foo_provider' => ['locales' => ['en', 'fr']],
            'bar_provider' => ['locales' => ['de', 'pl']],
        ]], enabledLocales: ['es']);

        $this->assertSame(['es', 'en', 'fr', 'de', 'pl'], $container->getParameter(TranslationBundle::PROVIDER_LOCALES_PARAMETER));
        $this->assertSame(['es', 'en', 'fr', 'de', 'pl'], $container->getDefinition('translation.provider_collection_factory')->getArgument(1));
    }

    public function testProviderDomainsCanBeKeyed()
    {
        $config = new Processor()->processConfiguration(new TranslationBundle()->getContainerExtension()->getConfiguration([], new ContainerBuilder()), [[
            'providers' => [
                'loco' => [
                    'dsn' => 'loco://API_KEY@default',
                    // as an XML configuration is converted
                    'domains' => [
                        ['key' => 'foo', 'value' => 'bar'],
                        ['key' => '', 'value' => '*'],
                    ],
                ],
            ],
        ]]);

        $this->assertSame(['foo' => 'bar', '' => '*'], $config['providers']['loco']['domains']);
    }

    public function testDataCollectorFollowsTheProfilerInDebugMode()
    {
        $container = $this->load([], debug: true, profiler: true);
        $this->assertTrue($container->hasDefinition('data_collector.translation'));
        $this->assertSame('translator', $container->getDefinition('translator.data_collector')->getDecoratedService()[0]);

        $this->assertFalse($this->load([], debug: true)->hasDefinition('data_collector.translation'));
        $this->assertFalse($this->load([], profiler: true)->hasDefinition('data_collector.translation'));
    }

    public function testLocaleSwitcherServiceRegistered()
    {
        $container = $this->load([], merge: false);
        $container->register('router.request_context', \stdClass::class);
        $container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $container->compile();

        $definition = $container->getDefinition('translation.locale_switcher');
        $localeAware = $definition->getArgument(1);

        $this->assertSame('en', $definition->getArgument(0));
        $this->assertEquals(new Reference('router.request_context', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE), $definition->getArgument(2));
        // the switcher drives the locale-aware services, so it must not be one of them
        $this->assertNotContains('translation.locale_switcher', array_map(strval(...), $localeAware instanceof TaggedIteratorArgument ? $localeAware->getValues() : $localeAware));
    }

    /**
     * @return list<string>
     */
    private function fallbackLocales(Definition $translator): array
    {
        foreach ($translator->getMethodCalls() as [$method, $arguments]) {
            if ('setFallbackLocales' === $method) {
                return $arguments[0];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $debug = false, bool $merge = true, bool $profiler = false, array $enabledLocales = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => $debug,
            'kernel.build_dir' => '/build',
            'kernel.cache_dir' => '/build',
            'kernel.project_dir' => __DIR__,
            'kernel.default_locale' => 'en',
            'kernel.enabled_locales' => $enabledLocales,
            'kernel.bundles_metadata' => [],
            'kernel.container_class' => 'TestContainer',
        ]));

        if ($profiler) {
            $container->register('profiler', \stdClass::class);
        }

        $bundle = new TranslationBundle();
        $bundle->build($container);
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('translation', $config);

        if (!$merge) {
            // the caller compiles, which runs the passes build() registered
            $container->getCompilerPassConfig()->setRemovingPasses([]);

            return $container;
        }

        new MergeExtensionConfigurationPass()->process($container);
        new RemoveMissingDependenciesPass()->process($container);

        return $container;
    }
}
