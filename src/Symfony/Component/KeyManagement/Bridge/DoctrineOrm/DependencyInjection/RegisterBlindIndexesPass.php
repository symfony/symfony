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
 * Hands {@see \Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\BlindIndexListener}
 * the blind indexes of the application, keyed by class name.
 *
 * Keyed by class rather than by service id, because that is what {@see BlindIndexed} names: an
 * entity says `Email::class`, which an application reads and a typo in which is a fatal error
 * rather than a tag nobody ever matches. Two services of the same class are refused for the same
 * reason, since the attribute would have no way of telling them apart.
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
            $class = $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass());

            if (isset($indexes[$class])) {
                throw new \InvalidArgumentException(\sprintf('Services "%s" and "%s" are both blind indexes of class "%s", which the "%s" attribute cannot tell apart. Give one of them a class of its own.', $indexes[$class], $id, $class, BlindIndexed::class));
            }

            $indexes[$class] = $id;
        }

        if (!$indexes) {
            $container->removeDefinition($this->listenerId);

            return;
        }

        $container->getDefinition($this->listenerId)
            ->setArgument(0, ServiceLocatorTagPass::register($container, array_map(static fn (string $id): Reference => new Reference($id), $indexes)));
    }
}
