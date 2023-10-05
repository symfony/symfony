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

use Symfony\Bundle\FeatureFlagsBundle\Command\FeatureFlagsDebugCommand;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('console.command.feature_flags_debug', FeatureFlagsDebugCommand::class)
        ->args([
            tagged_iterator('feature_flags.feature_provider', 'name'),
        ])
        ->tag('console.command')
    ;
};
