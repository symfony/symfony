<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\AttributeExtensionPass;
use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractRendererEngine;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Constraint;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Loader\LoaderInterface;

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class TwigExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('twig.php');

        if (method_exists(Environment::class, 'resetGlobals')) {
            $container->getDefinition('twig')->addTag('kernel.reset', ['method' => 'resetGlobals']);
        }

        if ($container::willBeAvailable('symfony/form', Form::class, ['symfony/twig-bundle'])) {
            $loader->load('form.php');

            if (is_subclass_of(AbstractRendererEngine::class, ResetInterface::class)) {
                $container->getDefinition('twig.form.engine')->addTag('kernel.reset', [
                    'method' => 'reset',
                ]);
            }
        }

        if ($container::willBeAvailable('symfony/console', Application::class, ['symfony/twig-bundle'])) {
            $loader->load('console.php');
        }

        if (!$container::willBeAvailable('symfony/translation', Translator::class, ['symfony/twig-bundle'])) {
            $container->removeDefinition('twig.translation.extractor');
        }

        if ($container::willBeAvailable('symfony/validator', Constraint::class, ['symfony/twig-bundle'])) {
            $loader->load('validator.php');
        }

        foreach ($configs as $key => $config) {
            if (isset($config['globals'])) {
                foreach ($config['globals'] as $name => $value) {
                    if (\is_array($value) && isset($value['key'])) {
                        $configs[$key]['globals'][$name] = [
                            'key' => $name,
                            'value' => $value,
                        ];
                    }
                }
            }
        }

        $configuration = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($configuration, $configs);

        if ($container::willBeAvailable('symfony/mailer', Mailer::class, ['symfony/twig-bundle'])) {
            $loader->load('mailer.php');

            if ($htmlToTextConverter = $config['mailer']['html_to_text_converter'] ?? null) {
                $container->getDefinition('twig.mime_body_renderer')->setArgument('$converter', new Reference($htmlToTextConverter));
            }

            if (ContainerBuilder::willBeAvailable('symfony/translation', LocaleSwitcher::class, ['symfony/framework-bundle'])) {
                $container->getDefinition('twig.mime_body_renderer')->setArgument('$localeSwitcher', new Reference('translation.locale_switcher', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
            }
        }

        if ($container::willBeAvailable('symfony/asset-mapper', AssetMapper::class, ['symfony/twig-bundle'])) {
            $loader->load('importmap.php');
        }

        $container->setParameter('twig.form.resources', $config['form_themes']);
        $container->setParameter('twig.default_path', $config['default_path']);
        $defaultTwigPath = $container->getParameterBag()->resolveValue($config['default_path']);

        $envConfiguratorDefinition = $container->getDefinition('twig.configurator.environment');
        $envConfiguratorDefinition->replaceArgument(0, $config['date']['format']);
        $envConfiguratorDefinition->replaceArgument(1, $config['date']['interval_format']);
        $envConfiguratorDefinition->replaceArgument(2, $config['date']['timezone']);
        $envConfiguratorDefinition->replaceArgument(3, $config['number_format']['decimals']);
        $envConfiguratorDefinition->replaceArgument(4, $config['number_format']['decimal_point']);
        $envConfiguratorDefinition->replaceArgument(5, $config['number_format']['thousands_separator']);

        $twigFilesystemLoaderDefinition = $container->getDefinition('twig.loader.native_filesystem');

        // register user-configured paths
        foreach ($config['paths'] as $path => $namespace) {
            if (!$namespace) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path]);
            } else {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, $namespace]);
            }
        }

        // paths are modified in ExtensionPass if forms are enabled
        $container->getDefinition('twig.template_iterator')->replaceArgument(1, $config['paths']);

        $container->getDefinition('twig.template_iterator')->replaceArgument(3, $config['file_name_pattern']);

        if ($container->hasDefinition('twig.command.lint')) {
            $container->getDefinition('twig.command.lint')->replaceArgument(1, $config['file_name_pattern'] ?: ['*.twig']);
        }

        foreach ($this->getBundleTemplatePaths($container, $config) as $name => $paths) {
            $namespace = $this->normalizeBundleName($name);
            foreach ($paths as $path) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, $namespace]);
            }

            if ($paths) {
                // the last path must be the bundle views directory
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, '!'.$namespace]);
            }
        }

        if (file_exists($defaultTwigPath)) {
            $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$defaultTwigPath]);
        }
        $container->addResource(new FileExistenceResource($defaultTwigPath));

        if (!empty($config['globals'])) {
            $def = $container->getDefinition('twig');
            foreach ($config['globals'] as $key => $global) {
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->addMethodCall('addGlobal', [$key, new Reference($global['id'])]);
                } else {
                    $def->addMethodCall('addGlobal', [$key, $global['value']]);
                }
            }
        }

        if (true === $config['cache']) {
            $autoReloadOrDefault = $container->getParameterBag()->resolveValue($config['auto_reload'] ?? $config['debug']);
            $buildDir = $container->getParameter('kernel.build_dir');
            $cacheDir = $container->getParameter('kernel.cache_dir');

            if ($autoReloadOrDefault || $cacheDir === $buildDir) {
                $config['cache'] = '%kernel.cache_dir%/twig';
            }
        }

        if (true === $config['cache']) {
            $config['cache'] = new Reference('twig.template_cache.chain');
        } else {
            $container->removeDefinition('twig.template_cache.chain');
            $container->removeDefinition('twig.template_cache.runtime_cache');
            $container->removeDefinition('twig.template_cache.readonly_cache');
            $container->removeDefinition('twig.template_cache.warmup_cache');

            if (false === $config['cache']) {
                $container->removeDefinition('twig.template_cache_warmer');
            } else {
                $container->getDefinition('twig.template_cache_warmer')->replaceArgument(2, null);
            }
        }

        if (isset($config['autoescape_service'])) {
            $config['autoescape'] = [new Reference($config['autoescape_service']), $config['autoescape_service_method'] ?? '__invoke'];
        } else {
            $config['autoescape'] = 'name';
        }

        $container->getDefinition('twig')->replaceArgument(1, array_intersect_key($config, [
            'debug' => true,
            'charset' => true,
            'base_template_class' => true,
            'strict_variables' => true,
            'autoescape' => true,
            'cache' => true,
            'auto_reload' => true,
            'optimizations' => true,
        ]));

        $container->registerForAutoconfiguration(ExtensionInterface::class)->addTag('twig.extension');
        $container->registerForAutoconfiguration(LoaderInterface::class)->addTag('twig.loader');
        $container->registerForAutoconfiguration(RuntimeExtensionInterface::class)->addTag('twig.runtime');

        $container->registerAttributeForAutoconfiguration(AsTwigFilter::class, AttributeExtensionPass::autoconfigureFromAttribute(...));
        $container->registerAttributeForAutoconfiguration(AsTwigFunction::class, AttributeExtensionPass::autoconfigureFromAttribute(...));
        $container->registerAttributeForAutoconfiguration(AsTwigTest::class, AttributeExtensionPass::autoconfigureFromAttribute(...));
    }

    private function getBundleTemplatePaths(ContainerBuilder $container, array $config): array
    {
        $bundleHierarchy = [];
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            $defaultOverrideBundlePath = $container->getParameterBag()->resolveValue($config['default_path']).'/bundles/'.$name;

            if (file_exists($defaultOverrideBundlePath)) {
                $bundleHierarchy[$name][] = $defaultOverrideBundlePath;
            }
            $container->addResource(new FileExistenceResource($defaultOverrideBundlePath));

            if (file_exists($dir = $bundle['path'].'/Resources/views') || file_exists($dir = $bundle['path'].'/templates')) {
                $bundleHierarchy[$name][] = $dir;
            }
            $container->addResource(new FileExistenceResource($dir));
        }

        return $bundleHierarchy;
    }

    private function normalizeBundleName(string $name): string
    {
        if (str_ends_with($name, 'Bundle')) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }

    public function getXsdValidationBasePath(): string|false
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/twig';
    }
}
