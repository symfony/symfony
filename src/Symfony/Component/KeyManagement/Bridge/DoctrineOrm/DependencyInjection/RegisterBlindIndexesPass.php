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
 * Hands the blind indexes of the application to the listener, keyed by the name each one carries.
 *
 * The listener is {@see \Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener}.
 *
 * Keyed by name rather than by service id, because that is what {@see BlindIndexed} names, and
 * because the name is already what the index derives its tags under: an entity and the key material
 * behind it then agree on one identifier instead of two. The name comes from the tag and not from
 * the service, since nothing of a `BlindIndex` or a `StoredKeyBlindIndex` is visible from the
 * outside. Two indexes under one name are refused: they would tag a value alike over a shared key,
 * and the attribute would have no way of telling them apart either.
 *
 * A service carries two entries of the tag when it is autoconfigured and tagged by hand, since
 * `ResolveInstanceofConditionalsPass` adds the bare one beside the explicit one rather than in its
 * place. Only the entries naming an index count, and a service naming none is what the
 * autoconfigured tag alone looks like: the application registered an index and never named it.
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
                if (isset($attributes['index'])) {
                    $declared[] = $container->getParameterBag()->resolveValue($attributes['index']);
                }
            }

            if (!$declared) {
                throw new \InvalidArgumentException(\sprintf('The "%s" tag of service "%s" must carry an "index", the name an entity states in its "%s" attribute to reach that index.', $this->tag, $id, BlindIndexed::class));
            }

            if (1 < \count($declared)) {
                throw new \InvalidArgumentException(\sprintf('Service "%s" is tagged "%s" under the names "%s", but an index derives its tags under one: a second name is a second index.', $id, $this->tag, implode('", "', $declared)));
            }

            $name = $declared[0];

            if (isset($indexes[$name])) {
                throw new \InvalidArgumentException(\sprintf('Services "%s" and "%s" are both blind indexes named "%s", which the "%s" attribute cannot tell apart. Give one of them a name of its own.', $indexes[$name], $id, $name, BlindIndexed::class));
            }

            $indexes[$name] = $id;
        }

        if (!$indexes) {
            $container->removeDefinition($this->listenerId);

            return;
        }

        $container->getDefinition($this->listenerId)
            ->setArgument(0, ServiceLocatorTagPass::register($container, array_map(static fn (string $id): Reference => new Reference($id), $indexes)));
    }
}
