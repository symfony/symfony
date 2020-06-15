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

use Symfony\Component\HttpKernel\EventListener\WelcomeListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('welcome.listener', WelcomeListener::class)
            ->args([
                param('kernel.project_dir'),
            ])
            ->tag('kernel.event_subscriber')
    ;
};
