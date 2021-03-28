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

use Symfony\Component\Config\Builder\ConfigBuilderGenerator;
use Symfony\Component\Config\Builder\ConfigBuilderGeneratorInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('config.builder_generator', ConfigBuilderGenerator::class)
        ->args([
                param('kernel.build_dir'),
            ])
        ->alias(ConfigBuilderGeneratorInterface::class, 'config.builder_generator')
    ;
};
