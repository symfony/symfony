<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\BlindIndexed;

/**
 * Hands the blind indexes of the application to the listener, keyed by the projection each derives through.
 *
 * The listener is {@see \Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener}.
 *
 * Keyed by projection rather than by service id, because that is what {@see BlindIndexed} names: an
 * entity says `Email::class`, which an application reads and a typo in which is a fatal error
 * rather than a tag nobody ever matches. The projection comes from the tag and not from the service
 * class, since every index is a `BlindIndex` or a `StoredKeyBlindIndex` and the class tells none of
 * them apart. Two indexes over one projection are refused, since the attribute would have no way of
 * telling them apart either.
 *
 * A service carries two entries of the tag when it is autoconfigured and tagged by hand, since
 * `ResolveInstanceofConditionalsPass` adds the bare one beside the explicit one rather than in its
 * place. Only the entries naming a projection count, and a service naming none is what the
 * autoconfigured tag alone looks like: the application registered an index and never said what it
 * indexes.
 *
 * The listener is removed when no index is registered, so that a flush does not walk its entities
 * for nothing.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class RegisterBlindIndexesPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $listenerId = 'key_management.blind_index_listener',
        private readonly string $tag = 'key_management.blind_index',
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition($this->listenerId)) {
            return;
        }

        $indexes = [];
        foreach ($container->findTaggedServiceIds($this->tag) as $id => $tags) {
            $declared = [];
            foreach ($tags as $attributes) {
                if (isset($attributes['projection'])) {
                    $declared[] = $container->getParameterBag()->resolveValue($attributes['projection']);
                }
            }

            if (!$declared) {
                throw new \InvalidArgumentException(\sprintf('The "%s" tag of service "%s" must carry a "projection", the class an entity names in its "%s" attribute to reach that index.', $this->tag, $id, BlindIndexed::class));
            }

            if (1 < \count($declared)) {
                throw new \InvalidArgumentException(\sprintf('Service "%s" is tagged "%s" for the projections "%s", but an index derives its tags through one: a second projection is a second index.', $id, $this->tag, implode('", "', $declared)));
            }

            $projection = $declared[0];

            if (isset($indexes[$projection])) {
                throw new \InvalidArgumentException(\sprintf('Services "%s" and "%s" are both blind indexes over the projection "%s", which the "%s" attribute cannot tell apart. Give one of them a projection of its own.', $indexes[$projection], $id, $projection, BlindIndexed::class));
            }

            $indexes[$projection] = $id;
        }

        if (!$indexes) {
            $container->removeDefinition($this->listenerId);

            return;
        }

        $container->getDefinition($this->listenerId)
            ->setArgument(0, ServiceLocatorTagPass::register($container, array_map(static fn (string $id): Reference => new Reference($id), $indexes)));
    }
}
