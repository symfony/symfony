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

use Symfony\Bridge\Twig\EventListener\TemplateAttributeListener;
use Symfony\Bridge\Twig\TemplateGuesser;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('twig.template_guesser', TemplateGuesser::class)
            ->args([
                service('kernel'),
            ])

        ->set('twig.template_attribute_listener', TemplateAttributeListener::class)
            ->args([
                service('twig.template_guesser'),
                service('twig'),
            ])
    ;
};
