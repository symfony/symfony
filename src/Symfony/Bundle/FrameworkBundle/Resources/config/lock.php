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

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Serializer\LockKeyNormalizer;
use Symfony\Component\Lock\Store\CombinedStore;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Lock\Strategy\ConsensusStrategy;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('lock.store.combined.abstract', CombinedStore::class)->abstract()
            ->args([abstract_arg('List of stores'), service('lock.strategy.majority')])

        ->set('lock.strategy.majority', ConsensusStrategy::class)

        ->set('lock.factory.abstract', LockFactory::class)->abstract()
            ->args([abstract_arg('Store')])
            ->call('setLogger', [service('logger')->ignoreOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'lock'])

        ->set('serializer.normalizer.lock_key', LockKeyNormalizer::class)
            ->tag('serializer.normalizer', ['built_in' => true, 'priority' => -880])

        ->set('.lock.flock.store', FlockStore::class)
            ->args([inline_service('string')->factory('implode')->args(['/', [
                inline_service('string')->factory('sys_get_temp_dir'),
                'symfony-lock',
                inline_service('string')->factory('hash')->args(['xxh64', '%kernel.project_dir%']),
            ]])])

        ->set('.lock.semaphore.store', SemaphoreStore::class)
            ->args(['%kernel.project_dir%'])
    ;
};
