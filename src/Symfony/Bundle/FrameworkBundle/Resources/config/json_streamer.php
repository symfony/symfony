<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\JsonStreamer\CacheWarmer\LazyGhostCacheWarmer;
use Symfony\Component\JsonStreamer\CacheWarmer\StreamerCacheWarmer;
use Symfony\Component\JsonStreamer\JsonStreamReader;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\JsonStreamer\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Read\AttributePropertyMetadataLoader as ReadAttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Read\DateTimeTypePropertyMetadataLoader as ReadDateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Write\AttributePropertyMetadataLoader as WriteAttributePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Mapping\Write\DateTimeTypePropertyMetadataLoader as WriteDateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;
use Symfony\Component\JsonStreamer\ValueTransformer\StringToDateTimeValueTransformer;

return static function (ContainerConfigurator $container) {
    $container->services()
        // stream reader/writer
        ->set('json_streamer.stream_writer', JsonStreamWriter::class)
            ->args([
                tagged_locator('json_streamer.value_transformer'),
                service('json_streamer.write.property_metadata_loader'),
                param('.json_streamer.stream_writers_dir'),
            ])
        ->set('json_streamer.stream_reader', JsonStreamReader::class)
            ->args([
                tagged_locator('json_streamer.value_transformer'),
                service('json_streamer.read.property_metadata_loader'),
                param('.json_streamer.stream_readers_dir'),
                param('.json_streamer.lazy_ghosts_dir'),
            ])
        ->alias(JsonStreamWriter::class, 'json_streamer.stream_writer')
        ->alias(JsonStreamReader::class, 'json_streamer.stream_reader')

        // metadata
        ->set('json_streamer.write.property_metadata_loader', PropertyMetadataLoader::class)
            ->args([
                service('type_info.resolver'),
            ])
        ->set('.json_streamer.write.property_metadata_loader.generic', GenericTypePropertyMetadataLoader::class)
            ->decorate('json_streamer.write.property_metadata_loader')
            ->args([
                service('.inner'),
                service('type_info.type_context_factory'),
            ])
        ->set('.json_streamer.write.property_metadata_loader.date_time', WriteDateTimeTypePropertyMetadataLoader::class)
            ->decorate('json_streamer.write.property_metadata_loader')
            ->args([
                service('.inner'),
            ])
        ->set('.json_streamer.write.property_metadata_loader.attribute', WriteAttributePropertyMetadataLoader::class)
            ->decorate('json_streamer.write.property_metadata_loader')
            ->args([
                service('.inner'),
                tagged_locator('json_streamer.value_transformer'),
                service('type_info.resolver'),
            ])

        ->set('json_streamer.read.property_metadata_loader', PropertyMetadataLoader::class)
            ->args([
                service('type_info.resolver'),
            ])
        ->set('.json_streamer.read.property_metadata_loader.generic', GenericTypePropertyMetadataLoader::class)
            ->decorate('json_streamer.read.property_metadata_loader')
            ->args([
                service('.inner'),
                service('type_info.type_context_factory'),
            ])
        ->set('.json_streamer.read.property_metadata_loader.date_time', ReadDateTimeTypePropertyMetadataLoader::class)
            ->decorate('json_streamer.read.property_metadata_loader')
            ->args([
                service('.inner'),
            ])
        ->set('.json_streamer.read.property_metadata_loader.attribute', ReadAttributePropertyMetadataLoader::class)
            ->decorate('json_streamer.read.property_metadata_loader')
            ->args([
                service('.inner'),
                tagged_locator('json_streamer.value_transformer'),
                service('type_info.resolver'),
            ])

        // value transformers
        ->set('json_streamer.value_transformer.date_time_to_string', DateTimeToStringValueTransformer::class)
            ->tag('json_streamer.value_transformer')

        ->set('json_streamer.value_transformer.string_to_date_time', StringToDateTimeValueTransformer::class)
            ->tag('json_streamer.value_transformer')

        // cache
        ->set('.json_streamer.cache_warmer.streamer', StreamerCacheWarmer::class)
            ->args([
                abstract_arg('streamable'),
                service('json_streamer.write.property_metadata_loader'),
                service('json_streamer.read.property_metadata_loader'),
                param('.json_streamer.stream_writers_dir'),
                param('.json_streamer.stream_readers_dir'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.cache_warmer')

        ->set('.json_streamer.cache_warmer.lazy_ghost', LazyGhostCacheWarmer::class)
            ->args([
                abstract_arg('streamable class names'),
                param('.json_streamer.lazy_ghosts_dir'),
            ])
            ->tag('kernel.cache_warmer')
    ;
};
