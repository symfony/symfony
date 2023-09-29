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

use Symfony\Bundle\FeatureFlagsBundle\Twig\FeatureEnabledExtension;

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    $services->set('feature_flags.twig_extension', FeatureEnabledExtension::class)
        ->args([
            service('feature_flags.feature_checker'),
        ])
        ->tag('twig.extension')
    ;
};
