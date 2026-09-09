<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\JsonStreamer\Attribute\JsonStreamable;
use Symfony\Component\JsonStreamer\DependencyInjection\CheckTypeInfoPass;
use Symfony\Component\JsonStreamer\DependencyInjection\DeprecateValueTransformerTagPass;
use Symfony\Component\JsonStreamer\DependencyInjection\StreamablePass;
use Symfony\Component\JsonStreamer\DependencyInjection\TransformerPass;
use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;
use Symfony\Component\TypeInfo\TypeInfoBundle;

/**
 * Provides the JSON streaming services.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(TypeInfoBundle::class, ignoreOnInvalid: true)]
class JsonStreamerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CheckTypeInfoPass());
        $container->addCompilerPass(new DeprecateValueTransformerTagPass());
        $container->addCompilerPass(new StreamablePass());
        $container->addCompilerPass(new TransformerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->arrayNode('default_options')
                    ->addDefaultsIfNotSet()
                    ->ignoreExtraKeys(false)
                    ->children()
                        ->booleanNode('include_null_properties')
                            ->info('Encode the properties with null value')
                            ->defaultFalse()
                        ->end()
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

        $configurator->import('Resources/config/json_streamer.php');

        $container->setParameter('.json_streamer.default_options', $config['default_options']);
        $container->setParameter('.json_streamer.stream_writers_dir', '%kernel.cache_dir%/json_streamer/stream_writer');
        $container->setParameter('.json_streamer.stream_readers_dir', '%kernel.cache_dir%/json_streamer/stream_reader');

        if (!interface_exists(CacheWarmerInterface::class)) {
            $container->removeDefinition('.json_streamer.cache_warmer.streamer');
        }

        $container->registerForAutoconfiguration(PropertyValueTransformerInterface::class)
            ->addTag('json_streamer.property_value_transformer');

        $container->registerForAutoconfiguration(ValueObjectTransformerInterface::class)
            ->addTag('json_streamer.value_object_transformer');

        $container->registerAttributeForAutoconfiguration(JsonStreamable::class, static function (ChildDefinition $definition, JsonStreamable $attribute): void {
            $definition->addTag('json_streamer.streamable', [
                'object' => $attribute->asObject,
                'list' => $attribute->asList,
            ])->addTag('container.excluded', ['source' => 'because it\'s a streamable JSON']);
        });
    }
}
