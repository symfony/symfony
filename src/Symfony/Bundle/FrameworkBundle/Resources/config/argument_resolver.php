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

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadataFactory;
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('argument_resolver.argument_metadata_factory', ArgumentMetadataFactory::class)

        ->set('argument_resolver.default', DefaultValueResolver::class)
            ->tag('argument_resolver.controller.value_resolver', ['priority' => -100, 'name' => DefaultValueResolver::class])
    ;
};
