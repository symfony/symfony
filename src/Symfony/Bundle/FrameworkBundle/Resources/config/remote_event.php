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

use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventHandler;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('remote_event.messenger.handler', ConsumeRemoteEventHandler::class)
            ->args([
                tagged_locator('remote_event.consumer', 'consumer'),
            ])
            ->tag('messenger.message_handler')
    ;
};
