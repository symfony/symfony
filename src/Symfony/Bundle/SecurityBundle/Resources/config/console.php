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

use Symfony\Bundle\SecurityBundle\Command\UserPasswordEncoderCommand;
use Symfony\Component\PasswordHasher\Command\UserPasswordHashCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.command.user_password_encoder', UserPasswordEncoderCommand::class)
            ->args([
                service('security.encoder_factory'),
                abstract_arg('encoders user classes'),
            ])
            ->tag('console.command', ['command' => 'security:encode-password'])
            ->deprecate('symfony/security-bundle', '5.3', 'The "%service_id%" service is deprecated, use "security.command.user_password_hash" instead.')
    ;

    $container->services()
        ->set('security.command.user_password_hash', UserPasswordHashCommand::class)
        ->args([
            service('security.password_hasher_factory'),
            abstract_arg('list of user classes'),
        ])
        ->tag('console.command')
    ;
};
