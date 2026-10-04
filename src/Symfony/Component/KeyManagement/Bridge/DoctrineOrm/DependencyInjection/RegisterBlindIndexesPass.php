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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\BlindIndex\AbstractBlindIndex;
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
 * That refusal only holds if the tag names what the index derives under, so the tag hands its name
 * to the `$name` argument of a `BlindIndex` or a `StoredKeyBlindIndex` that leaves it out, and one
 * stating another name is refused.
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

            $this->bindName($container, $id, $name);
            $indexes[$name] = $id;
        }

        if (!$indexes) {
            $container->removeDefinition($this->listenerId);

            return;
        }

        $container->getDefinition($this->listenerId)
            ->setArgument(0, ServiceLocatorTagPass::register($container, array_map(static fn (string $id): Reference => new Reference($id), $indexes)));
    }

    private function bindName(ContainerBuilder $container, string $id, string $name): void
    {
        $definition = $container->getDefinition($id);
        $class = $container->getParameterBag()->resolveValue($definition->getClass());

        // a child definition receives its arguments from its parent later on, and an index of the
        // application's own derives its tags however it chooses
        if ($definition instanceof ChildDefinition || !\is_string($class) || !is_subclass_of($class, AbstractBlindIndex::class)) {
            return;
        }

        $arguments = $definition->getArguments();
        $key = \array_key_exists('$name', $arguments) ? '$name' : (new \ReflectionParameter([$class, '__construct'], 'name'))->getPosition();

        if (!\array_key_exists($key, $arguments)) {
            $definition->setArgument('$name', $name);

            return;
        }

        $stated = $container->getParameterBag()->resolveValue($arguments[$key]);

        if (\is_string($stated) && $stated !== $name && $stated === $container->resolveEnvPlaceholders($stated)) {
            throw new \InvalidArgumentException(\sprintf('Service "%s" is tagged "%s" for the index "%s" but derives its tags under "%s": two indexes deriving under one name tag a value alike over a shared key, whatever their tags say. Leave its "$name" argument out, the tag provides it.', $id, $this->tag, $name, $stated));
        }
    }
}
