<?php

use MsgPhp\{Eav, User};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $container) {
    $container->extension('msgphp_user', [
        'class_mapping' => [
            User\Entity\User::class => \App\Entity\User\User::class,
            User\Entity\UserEmail::class => \App\Entity\User\UserEmail::class,
            User\Entity\Username::class => \App\Entity\User\Username::class,
        ],
        'username_lookup' => [
            ['target' => User\Entity\UserEmail::class, 'field' => 'email', 'mapped_by' => 'user'],
        ],
    ]);

    $container->parameters()
        ->set('msgphp.doctrine.mapping_config', [
            'key_max_length' => 191,
        ]);
};
