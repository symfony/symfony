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

use Symfony\Component\Form\Extension\Csrf\Type\FormTypeCsrfExtension;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('form.type_extension.csrf', FormTypeCsrfExtension::class)
            ->args([
                service('security.csrf.token_manager'),
                param('form.type_extension.csrf.enabled'),
                param('form.type_extension.csrf.field_name'),
                service('translator')->nullOnInvalid(),
                param('validator.translation_domain'),
                service('form.server_params'),
            ])
            ->tag('form.type_extension')
    ;
};
