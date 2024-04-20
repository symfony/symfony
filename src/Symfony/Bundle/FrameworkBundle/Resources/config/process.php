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

use Symfony\Component\Process\Messenger\RunProcessMessageHandler;

return static function (ContainerConfigurator $container) {
    $container
        ->services()
            ->set('process.messenger.process_message_handler', RunProcessMessageHandler::class)
                ->tag('messenger.message_handler')
    ;
};
