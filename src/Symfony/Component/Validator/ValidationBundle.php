<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Attribute\ExtendsValidationFor;
use Symfony\Component\Validator\Constraints\ExpressionLanguageProvider;
use Symfony\Component\Validator\DependencyInjection\AddAutoMappingConfigurationPass;
use Symfony\Component\Validator\DependencyInjection\AddConstraintValidatorsPass;
use Symfony\Component\Validator\DependencyInjection\AddValidatorInitializersPass;
use Symfony\Component\Validator\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Validator\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;

/**
 * Provides the services that validate objects against their constraints.
 */
#[RequiredBundle(ServicesBundle::class)]
class ValidationBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
        $container->addCompilerPass(new AddConstraintValidatorsPass());
        $container->addCompilerPass(new AddValidatorInitializersPass());
        $container->addCompilerPass(new AttributeMetadataPass());
        $container->addCompilerPass(new AddAutoMappingConfigurationPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->booleanNode('enable_attributes')->defaultTrue()->end()
                ->arrayNode('static_method')
                    ->acceptAndWrap(['string'])
                    ->defaultValue(['loadValidatorMetadata'])
                    ->prototype('scalar')->end()
                    ->treatFalseLike([])
                ->end()
                ->scalarNode('translation_domain')->defaultValue('validators')->end()
                ->enumNode('email_validation_mode')->values(['html5', 'html5-allow-no-tld', 'strict'])->defaultValue('html5')->end()
                ->arrayNode('mapping')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths', 'path')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('not_compromised_password')
                    ->canBeDisabled('When disabled, compromised passwords will be accepted as valid.')
                    ->children()
                        ->scalarNode('endpoint')
                            ->defaultNull()
                            ->info('API endpoint for the NotCompromisedPassword Validator.')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('disable_translation')
                    ->defaultFalse()
                ->end()
                ->booleanNode('property_metadata_existence_check')
                    ->info('When enabled, validateProperty() and validatePropertyValue() throw an exception if no metadata is found for the given property.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('auto_mapping')
                    ->info('A collection of namespaces for which auto-mapping will be enabled by default, or null to opt-in with the EnableAutoMapping constraint.')
                    ->example([
                        'App\\Entity\\' => [],
                        'App\\WithSpecificLoaders\\' => ['validator.property_info_loader'],
                    ])
                    ->useAttributeAsKey('namespace')
                    ->normalizeKeys(false)
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($values) {
                            foreach ($values as $k => $v) {
                                if (isset($v['service'])) {
                                    continue;
                                }

                                if (isset($v['namespace'])) {
                                    $values[$k]['services'] = [];
                                    continue;
                                }

                                if (!\is_array($v)) {
                                    $values[$v]['services'] = [];
                                    unset($values[$k]);
                                    continue;
                                }

                                $tmp = $v;
                                unset($values[$k]);
                                $values[$k]['services'] = $tmp;
                            }

                            return $values;
                        })
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('services', 'service')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ConstraintValidatorInterface::class)
            ->addTag('validator.constraint_validator');
        $container->registerForAutoconfiguration(GroupProviderInterface::class)
            ->addTag('validator.group_provider');
        $container->registerForAutoconfiguration(ObjectInitializerInterface::class)
            ->addTag('validator.initializer');
        $container->registerForAutoconfiguration(Constraint::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a validation constraint']);

        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/validator.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/validator_debug.php');
        }

        $validatorBuilder = $container->getDefinition('validator.builder');

        // FrameworkBundle defaults this parameter for the services that reference it whether or not
        // validation is enabled; the pass promotes the configured domain over it, whatever the load order
        $container->setParameter('.validator.translation_domain', $config['translation_domain']);

        $files = ['xml' => [], 'yml' => []];
        $this->registerValidatorMapping($container, $config, $files);

        if ($files['xml']) {
            $validatorBuilder->addMethodCall('addXmlMappings', [$files['xml']]);
        }

        if ($files['yml']) {
            $validatorBuilder->addMethodCall('addYamlMappings', [$files['yml']]);
        }

        $container->findDefinition('validator.email')->replaceArgument(0, $config['email_validation_mode']);

        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!$config['enable_attributes'] || !$container->getParameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $container->registerAttributeForAutoconfiguration(Constraint::class, static function (ChildDefinition $definition, Constraint $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('validator.attribute_metadata');
            });
        }

        $container->registerAttributeForAutoconfiguration(ExtendsValidationFor::class, static function (ChildDefinition $definition, ExtendsValidationFor $attribute) {
            $definition->addTag('validator.attribute_metadata', ['for' => $attribute->class])
                ->addTag('container.excluded', ['source' => 'because it\'s a validator constraint extension']);
        });

        if ($config['enable_attributes']) {
            $validatorBuilder->addMethodCall('enableAttributeMapping');
        }

        foreach ($config['static_method'] as $methodName) {
            $validatorBuilder->addMethodCall('addMethodMapping', [$methodName]);
        }

        if (!$container->getParameter('kernel.debug')) {
            $validatorBuilder->addMethodCall('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
        }

        if ($config['disable_translation']) {
            $validatorBuilder->addMethodCall('disableTranslation');
        }

        if ($config['property_metadata_existence_check']) {
            $validatorBuilder->addMethodCall('enablePropertyMetadataExistenceCheck');
        }

        $container->setParameter('validator.auto_mapping', $config['auto_mapping']);

        if (!class_exists(PropertyInfoLoader::class)) {
            $container->removeDefinition('validator.property_info_loader');
        }

        $container->getDefinition('validator.not_compromised_password')
            ->setArgument(2, $config['not_compromised_password']['enabled'])
            ->setArgument(3, $config['not_compromised_password']['endpoint'])
        ;

        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('validator.expression_language');
            $container->removeDefinition('validator.expression_language_provider');
        } elseif (!class_exists(ExpressionLanguageProvider::class)) {
            $container->removeDefinition('validator.expression_language_provider');
        }
    }

    private function registerValidatorMapping(ContainerBuilder $container, array $config, array &$files): void
    {
        $fileRecorder = static function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (!ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/validator'])) {
            $container->removeDefinition('validator.form.attribute_metadata');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if (
                $container->fileExists($file = $configDir.'/validation.yaml', false)
                || $container->fileExists($file = $configDir.'/validation.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($file = $configDir.'/validation.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/validation', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/validator', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);
                $container->addResource(new DirectoryResource($path, '/^$/'));
            } elseif ($container->fileExists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(\sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(\sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    /**
     * @param-immediately-invoked-callable $fileRecorder
     */
    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }
}
