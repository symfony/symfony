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

use Symfony\Component\Uid\Factory\NameBasedUuidFactory;
use Symfony\Component\Uid\Factory\RandomBasedUuidFactory;
use Symfony\Component\Uid\Factory\TimeBasedUuidFactory;
use Symfony\Component\Uid\Factory\UlidFactory;
use Symfony\Component\Uid\Factory\UuidFactory;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('ulid.factory', UlidFactory::class)
        ->alias(UlidFactory::class, 'ulid.factory')

        ->set('uuid.factory', UuidFactory::class)
        ->alias(UuidFactory::class, 'uuid.factory')

        ->set('name_based_uuid.factory', NameBasedUuidFactory::class)
            ->factory([service('uuid.factory'), 'nameBased'])
            ->args([abstract_arg('Please set the "framework.uid.name_based_uuid_namespace" configuration option to use the "name_based_uuid.factory" service')])
        ->alias(NameBasedUuidFactory::class, 'name_based_uuid.factory')

        ->set('random_based_uuid.factory', RandomBasedUuidFactory::class)
            ->factory([service('uuid.factory'), 'randomBased'])
        ->alias(RandomBasedUuidFactory::class, 'random_based_uuid.factory')

        ->set('time_based_uuid.factory', TimeBasedUuidFactory::class)
            ->factory([service('uuid.factory'), 'timeBased'])
        ->alias(TimeBasedUuidFactory::class, 'time_based_uuid.factory')
    ;
};
