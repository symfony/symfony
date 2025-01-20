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

use Symfony\Bundle\FrameworkBundle\Secrets\DotenvVault;
use Symfony\Bundle\FrameworkBundle\Secrets\SodiumVault;
use Symfony\Component\DependencyInjection\StaticEnvVarLoader;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('secrets.vault', SodiumVault::class)
            ->args([
                abstract_arg('Secret dir, set in FrameworkExtension'),
                service('secrets.decryption_key')->ignoreOnInvalid(),
                abstract_arg('Secret env var, set in FrameworkExtension'),
            ])

        ->set('secrets.env_var_loader', StaticEnvVarLoader::class)
            ->args([service('secrets.vault')])
            ->tag('container.env_var_loader')

        ->set('secrets.decryption_key')
            ->parent('container.env')
            ->args([abstract_arg('Decryption env var, set in FrameworkExtension')])

        ->set('secrets.local_vault', DotenvVault::class)
            ->args([abstract_arg('.env file path, set in FrameworkExtension')])
    ;
};
