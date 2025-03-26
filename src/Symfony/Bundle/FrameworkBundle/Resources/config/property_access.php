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

use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('property_accessor', PropertyAccessor::class)
            ->args([
                abstract_arg('magic methods allowed, set by the extension'),
                abstract_arg('throw exceptions, set by the extension'),
                service('cache.property_access')->ignoreOnInvalid(),
                service(PropertyReadInfoExtractorInterface::class)->nullOnInvalid(),
                service(PropertyWriteInfoExtractorInterface::class)->nullOnInvalid(),
            ])

        ->alias(PropertyAccessorInterface::class, 'property_accessor')
    ;
};
