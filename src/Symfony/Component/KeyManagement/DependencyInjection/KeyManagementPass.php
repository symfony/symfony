<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyStoreInterface;
use Symfony\Component\KeyManagement\Debug\TraceableDataKeyStore;
use Symfony\Component\KeyManagement\Debug\TraceableEnvelopeEncrypter;
use Symfony\Component\KeyManagement\Debug\TraceableKms;

/**
 * Traces the KMS clients, their envelope encrypters and the data key store for the profiler.
 *
 * The clients are decorated, since the tag the console commands look them up by must follow.
 * The store is wrapped instead: its "kernel.reset" tag would move to the decorator, which has
 * no forget() to offer, and the retained plaintexts would then survive a unit of work.
 * Wrapping leaves that tag where it belongs and points at the traced store what an application
 * gets through autowiring. The rewrapping half keeps resolving to the store itself, which is
 * what {@see TraceableDataKeyStore} deliberately does not claim.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
final class KeyManagementPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('key_management.data_collector')) {
            return;
        }

        $collector = new Reference('key_management.data_collector');
        $clients = [];

        foreach ($container->findTaggedServiceIds('key_management.client', true) as $id => $tags) {
            $clients[] = $name = $tags[0]['key'] ?? $id;

            $container->register('debug.'.$id, TraceableKms::class)
                ->setFactory([TraceableKms::class, 'wrap'])
                ->setArguments([new Reference('.inner'), $collector, $name])
                ->setDecoratedService($id);

            $envelopeId = 'key_management.envelope_encrypter.'.$name;
            if ($container->hasDefinition($envelopeId)) {
                $container->register('debug.'.$envelopeId, TraceableEnvelopeEncrypter::class)
                    ->setArguments([new Reference('.inner'), $collector, $name])
                    ->setDecoratedService($envelopeId);
            }
        }

        $container->getDefinition('key_management.data_collector')->setArgument(0, $clients);

        if (!$container->hasDefinition('key_management.store')) {
            return;
        }

        $container->register('debug.key_management.store', TraceableDataKeyStore::class)
            ->setArguments([new Reference('key_management.store'), $collector, 'store']);

        $container->setAlias(DataKeyStoreInterface::class, 'debug.key_management.store');

        $stored = $container->getDefinition('key_management.stored_envelope_encrypter');
        $stored->replaceArgument(0, new Reference('debug.key_management.store'));

        // the fallback reading self-contained envelopes is an envelope encrypter decorated above,
        // and reaching it through its decorator would record one read twice
        $fallback = $stored->getArgument(1);
        if ($fallback instanceof Reference && $container->hasDefinition('debug.'.$fallback)) {
            $stored->replaceArgument(1, new Reference('debug.'.$fallback.'.inner'));
        }

        $container->register('debug.key_management.stored_envelope_encrypter', TraceableEnvelopeEncrypter::class)
            ->setArguments([new Reference('.inner'), $collector, 'stored'])
            ->setDecoratedService('key_management.stored_envelope_encrypter');
    }
}
