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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;
use Symfony\Component\ObjectMapper\CacheWarmer\ObjectMapperCacheWarmer;
use Symfony\Component\ObjectMapper\Metadata\CacheClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\CachePropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\CachePropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Metadata\EnumMappingMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\PropertyTypeMappingMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Metadata\ReverseClassObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('object_mapper.metadata.cache.file', '%kernel.build_dir%/object_mapper.php')
    ;

    $container->services()
        ->set('object_mapper.metadata_factory', ReflectionObjectMapperMetadataFactory::class)
        ->alias(ObjectMapperMetadataFactoryInterface::class, 'object_mapper.metadata_factory')

        ->set('object_mapper.metadata_factory.reverse_class', ReverseClassObjectMapperMetadataFactory::class)
            ->decorate('object_mapper.metadata_factory')
            ->args([
                service('.inner'),
                abstract_arg('class_map'),
            ])

        ->set('object_mapper.metadata_factory.enum', EnumMappingMetadataFactory::class)
            ->decorate('object_mapper.metadata_factory')
            ->args([
                service('.inner'),
            ])

        ->set('object_mapper.metadata_factory.property_type', PropertyTypeMappingMetadataFactory::class)
            ->decorate('object_mapper.metadata_factory')
            ->args([
                service('.inner'),
            ])

        ->set('object_mapper.class_metadata_factory', ReflectionClassMetadataFactory::class)
            ->args([
                service('object_mapper.metadata_factory'),
            ])

        ->set('object_mapper.property_name_collection_factory', ReflectionPropertyNameCollectionFactory::class)

        ->set('object_mapper.property_metadata_factory', ReflectionPropertyMetadataFactory::class)
            ->args([
                service('object_mapper.metadata_factory'),
                service('object_mapper.class_metadata_factory'),
                service('property_accessor')->ignoreOnInvalid(),
            ])

        // Cache
        ->set('object_mapper.metadata.cache_warmer', ObjectMapperCacheWarmer::class)
            ->args([
                abstract_arg('class_map'),
                param('object_mapper.metadata.cache.file'),
                service('object_mapper.metadata_factory'),
                service('property_accessor')->ignoreOnInvalid(),
            ])
            ->tag('kernel.cache_warmer')
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('cache.object_mapper')
            ->parent('cache.system')
            ->private()
            ->tag('cache.pool')
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('object_mapper.metadata.cache.symfony', CacheItemPoolInterface::class)
            ->factory([PhpArrayAdapter::class, 'create'])
            ->args([param('object_mapper.metadata.cache.file'), service('cache.object_mapper')])

        ->set('object_mapper.metadata.cache_class_metadata_factory', CacheClassMetadataFactory::class)
            ->decorate('object_mapper.class_metadata_factory')
            ->args([
                service('.inner'),
                service('object_mapper.metadata.cache.symfony'),
            ])
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('object_mapper.metadata.cache_property_name_collection_factory', CachePropertyNameCollectionFactory::class)
            ->decorate('object_mapper.property_name_collection_factory')
            ->args([
                service('.inner'),
                service('object_mapper.metadata.cache.symfony'),
            ])
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('object_mapper.metadata.cache_property_metadata_factory', CachePropertyMetadataFactory::class)
            ->decorate('object_mapper.property_metadata_factory')
            ->args([
                service('.inner'),
                service('object_mapper.metadata.cache.symfony'),
                service('object_mapper.property_name_collection_factory'),
            ])
            ->tag('container.remove_if_missing', ['service' => 'cache.system'])

        ->set('object_mapper', ObjectMapper::class)
            ->args([
                service('object_mapper.metadata_factory'),
                service('property_accessor')->ignoreOnInvalid(),
                tagged_locator('object_mapper.transform_callable'),
                tagged_locator('object_mapper.condition_callable'),
                null,
                service('object_mapper.class_metadata_factory'),
                service('object_mapper.property_name_collection_factory'),
                service('object_mapper.property_metadata_factory'),
            ])
        ->alias(ObjectMapperInterface::class, 'object_mapper')
    ;
};
