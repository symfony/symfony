<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use PhpParser\Parser;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Form;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Translation\DependencyInjection\DataCollectorTranslatorPass;
use Symfony\Component\Translation\DependencyInjection\LoggingTranslatorPass;
use Symfony\Component\Translation\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Translation\DependencyInjection\TranslationDumperPass;
use Symfony\Component\Translation\DependencyInjection\TranslationExtractorPass;
use Symfony\Component\Translation\DependencyInjection\TranslatorPass;
use Symfony\Component\Translation\DependencyInjection\TranslatorPathsPass;
use Symfony\Component\Validator\Validation;

/**
 * Provides the services that translate messages.
 */
#[RequiredBundle(ServicesBundle::class)]
class TranslationBundle extends AbstractBundle
{
    /**
     * The paths the translation console commands of FrameworkBundle read, and the locales they push and pull.
     */
    public const TRANS_PATHS_PARAMETER = '.translator.trans_paths';
    public const PATHS_PARAMETER = '.translator.paths';
    public const DEFAULT_PATH_PARAMETER = '.translator.default_path';
    public const PROVIDER_LOCALES_PARAMETER = '.translator.provider_locales';

    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
        $container->addCompilerPass(new DataCollectorTranslatorPass());
        $container->addCompilerPass(new LoggingTranslatorPass());
        $container->addCompilerPass(new TranslationExtractorPass());
        $container->addCompilerPass(new TranslationDumperPass());
        // must be registered as late as possible to get access to all Twig paths registered in
        // twig.template_iterator definition
        $container->addCompilerPass(new TranslatorPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -32);
        $container->addCompilerPass(new TranslatorPathsPass(), PassConfig::TYPE_AFTER_REMOVING);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->arrayNode('fallbacks', 'fallback')
                    ->info('Defaults to the value of "default_locale".')
                    ->acceptAndWrap(['string'])
                    ->prototype('scalar')->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('logging')->defaultFalse()->end()
                ->scalarNode('formatter')->defaultValue('translator.formatter.default')->end()
                ->scalarNode('cache_dir')->defaultValue('%kernel.cache_dir%/translations')->end()
                ->scalarNode('default_path')
                    ->info('The default path used to load translations.')
                    ->defaultValue('%kernel.project_dir%/translations')
                ->end()
                ->arrayNode('paths', 'path')
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('pseudo_localization')
                    ->canBeEnabled()
                    ->children()
                        ->booleanNode('accents')->defaultTrue()->end()
                        ->floatNode('expansion_factor')
                            ->min(1.0)
                            ->defaultValue(1.0)
                        ->end()
                        ->booleanNode('brackets')->defaultTrue()->end()
                        ->booleanNode('parse_html')->defaultFalse()->end()
                        ->arrayNode('localizable_html_attributes', 'localizable_html_attribute')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('providers', 'provider')
                    ->info('Translation providers you can read/write your translations from.')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('dsn')->end()
                            ->arrayNode('domains', 'domain')
                                ->useAttributeAsKey('key')
                                ->prototype('scalar')->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('locales', 'locale')
                                ->prototype('scalar')->end()
                                ->defaultValue([])
                                ->info('If not set, all locales listed under framework.enabled_locales are used.')
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('globals', 'global')
                    ->info('Global parameters.')
                    ->example(['app_version' => 3.14])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->acceptAndWrap(['string'], 'value')
                        ->children()
                            ->variableNode('value')->end()
                            ->stringNode('message')->end()
                            ->arrayNode('parameters', 'parameter')
                                ->normalizeKeys(false)
                                ->useAttributeAsKey('name')
                                ->scalarPrototype()->end()
                            ->end()
                            ->stringNode('domain')->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => !(isset($v['value']) xor isset($v['message'])))
                            ->thenInvalid('The "globals" parameter should be either a string or an array with a "value" or a "message" key')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        // support for translations is included by default in the Form and Validator components, so an
        // identity translator has to answer even when translation itself is turned off
        $configurator->import('Resources/config/identity_translator.php');

        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/translation.php');
        $configurator->import('Resources/config/translation_providers.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/translation_debug.php');
            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (interface_exists(Parser::class)) {
            $container->removeDefinition('translation.extractor.php');
        } else {
            $container->removeDefinition('translation.extractor.php_ast');
        }

        // use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default')->setPublic(true);
        $container->setAlias('translator.formatter', new Alias($config['formatter'], false));

        // the locale parameters belong to another extension, so they are only referenced here and
        // resolved at compile time
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', [$config['fallbacks'] ?: ['%kernel.default_locale%']]);

        $defaultOptions = $translator->getArgument(4);
        $defaultOptions['cache_dir'] = $config['cache_dir'];
        $translator->setArgument(4, $defaultOptions);
        $translator->setArgument(5, '%kernel.enabled_locales%');

        $container->setParameter('translator.logging', $config['logging']);
        $container->setParameter('translator.default_path', $config['default_path']);

        [$dirs, $transPaths, $nonExistingDirs] = $this->discoverTranslationDirs($config, $container);

        $container->setParameter(self::TRANS_PATHS_PARAMETER, $transPaths);
        $container->setParameter(self::PATHS_PARAMETER, $config['paths']);
        $container->setParameter(self::DEFAULT_PATH_PARAMETER, $config['default_path']);

        if ($dirs) {
            $translator->replaceArgument(4, [
                ...$translator->getArgument(4),
                ...$this->buildResourceOptions($dirs, $nonExistingDirs, $container),
            ]);
        }

        foreach ($config['globals'] as $name => $global) {
            $translator->addMethodCall('addGlobalParameter', [$name, $global['value'] ?? new Definition(TranslatableMessage::class, [$global['message'], $global['parameters'] ?? [], $global['domain'] ?? null])]);
        }

        if ($config['pseudo_localization']['enabled']) {
            $options = $config['pseudo_localization'];
            unset($options['enabled']);

            $container
                ->register('translator.pseudo', PseudoLocalizationTranslator::class)
                ->setDecoratedService('translator', null, -1) // lower priority than "translator.data_collector"
                ->setArguments([
                    new Reference('translator.pseudo.inner'),
                    $options,
                ]);
        }

        $classToServices = [
            Bridge\Crowdin\CrowdinProviderFactory::class => ['symfony/crowdin-translation-provider', ['translation.provider_factory.crowdin', 'translation.provider_factory.crowdin.http_client']],
            Bridge\Loco\LocoProviderFactory::class => ['symfony/loco-translation-provider', ['translation.provider_factory.loco', 'translation.provider_factory.loco.http_client']],
            Bridge\Lokalise\LokaliseProviderFactory::class => ['symfony/lokalise-translation-provider', ['translation.provider_factory.lokalise']],
            Bridge\Phrase\PhraseProviderFactory::class => ['symfony/phrase-translation-provider', ['translation.provider_factory.phrase']],
            Bridge\PoEditor\PoEditorProviderFactory::class => ['symfony/po-editor-translation-provider', ['translation.provider_factory.poeditor']],
        ];

        $parentPackages = ['symfony/translation', 'symfony/http-client'];

        foreach ($classToServices as $class => [$package, $services]) {
            if (ContainerBuilder::willBeAvailable($package, $class, $parentPackages)) {
                continue;
            }

            foreach ($services as $service) {
                $container->removeDefinition($service);
            }
        }

        if (!$config['providers']) {
            return;
        }

        // RemoveMissingDependenciesPass merges these with the enabled locales, which are known by then
        $container->setParameter(self::PROVIDER_LOCALES_PARAMETER, array_merge(...array_column($config['providers'], 'locales')));
        $container->getDefinition('translation.provider_collection')->setArgument(0, $config['providers']);
    }

    /**
     * @return array{list<string>, list<string>, list<string>}
     */
    private function discoverTranslationDirs(array $config, ContainerBuilder $container): array
    {
        $dirs = [];
        $transPaths = [];
        $nonExistingDirs = [];

        if (ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/translation'])) {
            $r = new \ReflectionClass(Validation::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/translation'])) {
            $r = new \ReflectionClass(Form::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/security-core', AuthenticationException::class, ['symfony/translation'])) {
            $r = new \ReflectionClass(AuthenticationException::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName(), 2).'/Resources/translations';
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/translations') || $container->fileExists($dir = $bundle['path'].'/translations')) {
                $dirs[] = $transPaths[] = $dir;
            } else {
                $nonExistingDirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if (!$container->fileExists($dir)) {
                throw new \UnexpectedValueException(\sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }

            $dirs[] = $transPaths[] = $dir;
        }

        $defaultDir = $container->getParameterBag()->resolveValue($config['default_path']);

        if (null === $defaultDir) {
            // allow null
        } elseif ($container->fileExists($defaultDir)) {
            $dirs[] = $defaultDir;
        } else {
            $nonExistingDirs[] = $defaultDir;
        }

        return [$dirs, $transPaths, $nonExistingDirs];
    }

    private function buildResourceOptions(array $dirs, array $nonExistingDirs, ContainerBuilder $container): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $finder = Finder::create()
                ->followLinks()
                ->files()
                ->filter(static fn (\SplFileInfo $file) => 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename()))
                ->in($dir)
                ->sortByName()
            ;
            foreach ($finder as $file) {
                $fileNameParts = explode('.', basename($file));
                $locale = $fileNameParts[\count($fileNameParts) - 2];
                $files[$locale][] = (string) $file;
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        $scannedDirectories = array_merge($dirs, $nonExistingDirs);

        return [
            'resource_files' => $files,
            'scanned_directories' => $scannedDirectories,
            'cache_vary' => [
                'scanned_directories' => array_map(static fn ($dir) => str_starts_with($dir, $projectDir.'/') ? substr($dir, 1 + \strlen($projectDir)) : $dir, $scannedDirectories),
            ],
        ];
    }
}
