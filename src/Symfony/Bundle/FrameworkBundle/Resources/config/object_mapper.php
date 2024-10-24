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

use Symfony\Component\ObjectMapper\CallablesLocator;
use Symfony\Component\ObjectMapper\Metadata\MapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionMapperMetadataFactory;
use Symfony\Component\ObjectMapper\ObjectMapper;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('object_mapper.metadata_factory', ReflectionMapperMetadataFactory::class)
        ->alias(ReflectionMapperMetadataFactory::class, 'object_mapper.metadata_factory')
        ->alias(MapperMetadataFactoryInterface::class, 'object_mapper.metadata_factory')

        ->set('object_mapper', ObjectMapper::class)
            ->args([
                service('object_mapper.metadata_factory')->ignoreOnInvalid(),
                service('property_accessor')->ignoreOnInvalid(),
                tagged_locator('object_mapper.callable'),
            ])
        ->alias(ObjectMapper::class, 'object_mapper')
        ->alias(ObjectMapperInterface::class, 'object_mapper')
    ;
};
