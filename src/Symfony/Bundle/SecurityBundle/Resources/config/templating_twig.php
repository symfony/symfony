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

use Symfony\Bridge\Twig\Extension\LogoutUrlExtension;
use Symfony\Bridge\Twig\Extension\SecurityExtension;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.extension.logout_url', LogoutUrlExtension::class)
            ->args([
                service('security.logout_url_generator'),
            ])
            ->tag('twig.extension')

        ->set('twig.extension.security', SecurityExtension::class)
            ->args([
                service('security.authorization_checker')->ignoreOnInvalid(),
                service('security.impersonate_url_generator')->ignoreOnInvalid(),
            ])
            ->tag('twig.extension')
    ;
};
