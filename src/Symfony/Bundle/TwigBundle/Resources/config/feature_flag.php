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

use Symfony\Bridge\Twig\Extension\FeatureFlagExtension;
use Symfony\Bridge\Twig\Extension\FeatureFlagRuntime;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('twig.runtime.feature_flag', FeatureFlagRuntime::class)
            ->args([service('feature_flag.feature_checker')->nullOnInvalid()])
            ->tag('twig.runtime')

        ->set('twig.extension.feature_flag', FeatureFlagExtension::class)
            ->tag('twig.extension')
    ;
};
