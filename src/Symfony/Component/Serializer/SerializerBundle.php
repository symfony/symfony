<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

use Seld\JsonLint\JsonParser;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Serializer\Attribute as SerializerMapping;
use Symfony\Component\Serializer\Attribute\ExtendsSerializationFor;
use Symfony\Component\Serializer\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Serializer\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Serializer\DependencyInjection\SerializerPass;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides the services that serialize and deserialize objects.
 */
#[RequiredBundle(ServicesBundle::class)]
class SerializerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        // must run first so that SerializerPass does not wire what it removes
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
        $container->addCompilerPass(new SerializerPass());
        $container->addCompilerPass(new AttributeMetadataPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $defaultContextNode = static fn () => (new NodeBuilder())
            ->arrayNode('default_context')
                ->useAttributeAsKey('key')
                ->normalizeKeys(false)
                ->defaultValue([])
                ->prototype('variable')->end()
        ;

        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->booleanNode('enable_attributes')->defaultTrue()->end()
                ->scalarNode('name_converter')->end()
                ->scalarNode('circular_reference_handler')->end()
                ->scalarNode('max_depth_handler')->end()
                ->arrayNode('mapping')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths', 'path')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->append($defaultContextNode())
                ->arrayNode('named_serializers', 'named_serializer')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name_converter')->end()
                            ->append($defaultContextNode())
                            ->booleanNode('include_built_in_normalizers')
                                ->info('Whether to include the built-in normalizers')
                                ->defaultTrue()
                            ->end()
                            ->booleanNode('include_built_in_encoders')
                                ->info('Whether to include the built-in encoders')
                                ->defaultTrue()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn ($v) => isset($v['default']))
                        ->thenInvalid('"default" is a reserved name.')
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/serializer.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/serializer_debug.php');
        }

        $container->registerForAutoconfiguration(EncoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(DecoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(NormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(DenormalizerInterface::class)
            ->addTag('serializer.normalizer');

        // carried over from the configuration tree, which cannot read kernel.debug
        if ($container->getParameter('kernel.debug') && class_exists(JsonParser::class)) {
            $config['default_context'] += [JsonDecode::DETAILED_ERROR_MESSAGES => true];

            foreach ($config['named_serializers'] as $name => $serializer) {
                $config['named_serializers'][$name]['default_context'] += [JsonDecode::DETAILED_ERROR_MESSAGES => true];
            }
        }

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!class_exists(Headers::class)) {
            $container->removeDefinition('serializer.normalizer.mime_message');
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('serializer.mapping.cache_class_metadata_factory');
        }

        $serializerLoaders = [];

        // When attributes are disabled, it means from runtime-discovery only; autoconfiguration should still happen.
        // And when runtime-discovery of attributes is enabled, we can skip compile-time autoconfiguration in debug mode.
        if (!($config['enable_attributes'] ?? false) || !$container->getParameter('kernel.debug')) {
            // The $reflector argument hints at where the attribute could be used
            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata');
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Context::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Groups::class, $configurator);

            $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionMethod|\ReflectionProperty $reflector) {
                $definition->addTag('serializer.attribute_metadata');
            };
            $container->registerAttributeForAutoconfiguration(SerializerMapping\Ignore::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\MaxDepth::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedName::class, $configurator);
            $container->registerAttributeForAutoconfiguration(SerializerMapping\SerializedPath::class, $configurator);

            $container->registerAttributeForAutoconfiguration(SerializerMapping\DiscriminatorMap::class, static function (ChildDefinition $definition) {
                $definition->addTag('serializer.attribute_metadata');
            });
        }

        $container->registerAttributeForAutoconfiguration(SerializerMapping\DiscriminatorMapType::class, static function (ChildDefinition $definition, SerializerMapping\DiscriminatorMapType $attribute) {
            $definition->addTag('serializer.attribute_metadata', ['for' => $attribute->class, 'type' => $attribute->type, 'discriminator_map_type' => true]);
        });

        $serializerLoaders[] = new Reference('serializer.mapping.attribute_loader');

        $container->getDefinition('serializer.mapping.attribute_loader')
            ->replaceArgument(0, $config['enable_attributes'] ?? false);

        $parameterBag = $container->getParameterBag();
        // mapping files are collected from the filesystem as literals and handed back to the container
        $fileRecorder = static function ($extension, $path) use (&$serializerLoaders, $parameterBag) {
            $definition = new Definition(\in_array($extension, ['yaml', 'yml'], true) ? YamlFileLoader::class : XmlFileLoader::class, [$parameterBag->escapeValue($path)]);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $bundlePath = $parameterBag->unescapeValue($bundle['path']);
            $configDir = is_dir($bundlePath.'/Resources/config') ? $bundlePath.'/Resources/config' : $bundlePath.'/config';

            if ($container->fileExists($file = $configDir.'/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if (
                $container->fileExists($file = $configDir.'/serialization.yaml', false)
                || $container->fileExists($file = $configDir.'/serialization.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/serialization', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $parameterBag->unescapeValue($container->getParameter('kernel.project_dir'));
        if ($container->fileExists($dir = $projectDir.'/config/serializer', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);

        $chainLoader->replaceArgument(0, $serializerLoaders);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);

        if ($config['name_converter'] ?? false) {
            $container->setParameter('.serializer.name_converter', $config['name_converter']);
            $container->getDefinition('serializer.name_converter.metadata_aware')->setArgument(1, new Reference($config['name_converter']));
        }

        $defaultContext = $config['default_context'] ?? [];

        if ($defaultContext) {
            $container->setParameter('serializer.default_context', $defaultContext);
        }

        if ($config['circular_reference_handler'] ?? false) {
            $container->setParameter('.serializer.circular_reference_handler', $config['circular_reference_handler']);
        }

        if ($config['max_depth_handler'] ?? false) {
            $container->setParameter('.serializer.max_depth_handler', $config['max_depth_handler']);
        }

        $container->getDefinition('serializer.normalizer.property')->setArgument(5, $defaultContext);

        $container->setParameter('.serializer.named_serializers', $config['named_serializers'] ?? []);

        $container->registerAttributeForAutoconfiguration(ExtendsSerializationFor::class, static function (ChildDefinition $definition, ExtendsSerializationFor $attribute) {
            $definition->addTag('serializer.attribute_metadata', ['for' => $attribute->class])
                ->addTag('container.excluded', ['source' => 'because it\'s a serializer metadata extension']);
        });
    }

    private function registerMappingFilesFromConfig(ContainerBuilder $container, array $config, callable $fileRecorder): void
    {
        foreach ($container->getParameterBag()->unescapeValue($config['mapping']['paths']) as $path) {
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

    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }
}
