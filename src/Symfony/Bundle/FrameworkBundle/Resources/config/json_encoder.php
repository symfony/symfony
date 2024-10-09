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

use Symfony\Component\JsonEncoder\CacheWarmer\EncoderDecoderCacheWarmer;
use Symfony\Component\JsonEncoder\CacheWarmer\LazyGhostCacheWarmer;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DateTimeDenormalizer;
use Symfony\Component\JsonEncoder\Encode\Normalizer\DateTimeNormalizer;
use Symfony\Component\JsonEncoder\JsonDecoder;
use Symfony\Component\JsonEncoder\JsonEncoder;
use Symfony\Component\JsonEncoder\Mapping\Decode\AttributePropertyMetadataLoader as DecodeAttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Decode\DateTimeTypePropertyMetadataLoader as DecodeDateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader as EncodeAttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\Encode\DateTimeTypePropertyMetadataLoader as EncodeDateTimeTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\GenericTypePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;

return static function (ContainerConfigurator $container) {
    $container->services()
        // encoder/decoder
        ->set('json_encoder.encoder', JsonEncoder::class)
            ->args([
                tagged_locator('json_encoder.normalizer'),
                service('json_encoder.encode.property_metadata_loader'),
                param('json_encoder.encoders_dir'),
            ])
        ->set('json_encoder.decoder', JsonDecoder::class)
            ->args([
                tagged_locator('json_encoder.denormalizer'),
                service('json_encoder.decode.property_metadata_loader'),
                param('json_encoder.decoders_dir'),
                param('json_encoder.lazy_ghosts_dir'),
            ])
        ->alias(JsonEncoder::class, 'json_encoder.encoder')
        ->alias(JsonDecoder::class, 'json_encoder.decoder')

        // metadata
        ->stack('json_encoder.encode.property_metadata_loader', [
            inline_service(EncodeAttributePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                    tagged_locator('json_encoder.normalizer'),
                ]),
            inline_service(EncodeDateTimeTypePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                ]),
            inline_service(GenericTypePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                    service('type_info.type_context_factory'),
                ]),
            inline_service(PropertyMetadataLoader::class)
                ->args([
                    service('type_info.resolver'),
                ]),
        ])

        ->stack('json_encoder.decode.property_metadata_loader', [
            inline_service(DecodeAttributePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                    tagged_locator('json_encoder.denormalizer'),
                ]),
            inline_service(DecodeDateTimeTypePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                ]),
            inline_service(GenericTypePropertyMetadataLoader::class)
                ->args([
                    service('.inner'),
                    service('type_info.type_context_factory'),
                ]),
            inline_service(PropertyMetadataLoader::class)
                ->args([
                    service('type_info.resolver'),
                ]),
        ])

        // normalizers/denormalizers
        ->set('json_encoder.normalizer.date_time', DateTimeNormalizer::class)
            ->tag('json_encoder.normalizer')
        ->set('json_encoder.denormalizer.date_time', DateTimeDenormalizer::class)
            ->args([
                false,
            ])
            ->tag('json_encoder.denormalizer')
        ->set('json_encoder.denormalizer.date_time_immutable', DateTimeDenormalizer::class)
            ->args([
                true,
            ])
            ->tag('json_encoder.denormalizer')

        // cache
        ->set('.json_encoder.cache_warmer.encoder_decoder', EncoderDecoderCacheWarmer::class)
            ->args([
                tagged_iterator('json_encoder.encodable'),
                service('json_encoder.encode.property_metadata_loader'),
                service('json_encoder.decode.property_metadata_loader'),
                param('json_encoder.encoders_dir'),
                param('json_encoder.decoders_dir'),
                param('json_encoder.force_encode_chunks'),
                service('logger')->ignoreOnInvalid(),
            ])
            ->tag('kernel.cache_warmer')

        ->set('.json_encoder.cache_warmer.lazy_ghost', LazyGhostCacheWarmer::class)
            ->args([
                tagged_iterator('json_encoder.encodable'),
                param('json_encoder.lazy_ghosts_dir'),
            ])
            ->tag('kernel.cache_warmer')
    ;
};
