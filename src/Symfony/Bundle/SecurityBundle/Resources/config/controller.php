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

use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;
use Symfony\Component\Security\Http\EventListener\SecurityAttributeListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.is_granted_attribute_listener', IsGrantedAttributeListener::class)
            ->args([
                service('argument_name_convertor'),
                service('security.authorization_checker')->ignoreOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')

        ->set('security.security_attribute_listener', SecurityAttributeListener::class)
            ->args([
                service('argument_name_convertor'),
                service('security.expression_language')->ignoreOnInvalid(),
                service('security.authentication.trust_resolver')->ignoreOnInvalid(),
                service('security.role_hierarchy')->ignoreOnInvalid(),
                service('security.token_storage')->ignoreOnInvalid(),
                service('security.authorization_checker')->ignoreOnInvalid(),
                service('logger')->ignoreOnInvalid(),
            ])
        ->tag('kernel.event_subscriber')
    ;
};
