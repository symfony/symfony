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

use Symfony\Component\HttpKernel\EventListener\FragmentListener;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('fragment.listener', FragmentListener::class)
            ->args([service('uri_signer'), param('fragment.path')])
            ->tag('kernel.event_subscriber')
    ;
};
