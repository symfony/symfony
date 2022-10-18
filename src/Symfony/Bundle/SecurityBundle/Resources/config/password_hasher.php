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

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('security.password_hasher_factory', PasswordHasherFactory::class)
            ->args([[]])
        ->alias(PasswordHasherFactoryInterface::class, 'security.password_hasher_factory')

        ->set('security.user_password_hasher', UserPasswordHasher::class)
            ->args([service('security.password_hasher_factory')])
        ->alias('security.password_hasher', 'security.user_password_hasher')
        ->alias(UserPasswordHasherInterface::class, 'security.password_hasher')
    ;
};
