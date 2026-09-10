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

use Symfony\Component\Routing\Command\RouterMatchCommand;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('console.command.router_match', RouterMatchCommand::class)
            ->args([
                service('router'),
                tagged_iterator('routing.expression_language_provider'),
            ])
            ->tag('console.command')
    ;
};
