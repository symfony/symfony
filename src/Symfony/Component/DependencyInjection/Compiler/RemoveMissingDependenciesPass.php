<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Removes the definitions tagged with "container.remove_if_missing" whose conditions are not met.
 *
 * A condition names a service, a class, or a package together with one of its classes. All the
 * attributes of a tag must hold, and so must all the tags of a definition; a "service" attribute
 * holding a list is met by any one of them. Removing a definition can turn another condition
 * false, so the container is swept until it settles.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RemoveMissingDependenciesPass implements CompilerPassInterface
{
    private const TAG_NAME = 'container.remove_if_missing';

    private const ATTRIBUTES = ['service', 'class', 'package', 'parent_packages'];

    public function process(ContainerBuilder $container): void
    {
        if (!$tagged = $container->findTaggedServiceIds(self::TAG_NAME)) {
            return;
        }

        foreach ($tagged as $id => $tags) {
            foreach ($tags as $tag) {
                $this->validate($id, $tag);
            }
        }

        do {
            $removed = false;

            foreach ($tagged as $id => $tags) {
                if (!$container->hasDefinition($id)) {
                    continue;
                }

                foreach ($tags as $tag) {
                    if ($this->holds($container, $tag)) {
                        continue;
                    }

                    $this->remove($container, $id, $this->reason($tag));
                    $removed = true;

                    break;
                }
            }
        } while ($removed);
    }

    private function validate(string $id, array $tag): void
    {
        if ($unknown = array_diff(array_keys($tag), self::ATTRIBUTES)) {
            throw new InvalidArgumentException(\sprintf('Invalid "%s" tag for service "%s": unknown attribute "%s", expected one of "%s".', self::TAG_NAME, $id, reset($unknown), implode('", "', self::ATTRIBUTES)));
        }

        if (!isset($tag['service']) && !isset($tag['class'])) {
            throw new InvalidArgumentException(\sprintf('Invalid "%s" tag for service "%s": at least one of the "service" or "class" attributes is required.', self::TAG_NAME, $id));
        }

        if (!isset($tag['class']) && (isset($tag['package']) || isset($tag['parent_packages']))) {
            throw new InvalidArgumentException(\sprintf('Invalid "%s" tag for service "%s": the "package" and "parent_packages" attributes require the "class" one.', self::TAG_NAME, $id));
        }
    }

    private function holds(ContainerBuilder $container, array $tag): bool
    {
        if (isset($tag['service'])) {
            $found = false;

            foreach ((array) $tag['service'] as $service) {
                if ($found = $this->has($container, $service)) {
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        if (!isset($tag['class'])) {
            return true;
        }

        if (!isset($tag['package'])) {
            return class_exists($tag['class']) || interface_exists($tag['class'], false) || trait_exists($tag['class'], false);
        }

        $parentPackages = $tag['parent_packages'] ?? [];

        return $container::willBeAvailable($tag['package'], $tag['class'], (array) $parentPackages);
    }

    /**
     * Aliases are followed by hand: the container answers true for an alias whose target is gone.
     */
    private function has(ContainerBuilder $container, string $id): bool
    {
        $seen = [];

        while ($container->hasAlias($id)) {
            if (isset($seen[$id])) {
                return false;
            }

            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        }

        return $container->hasDefinition($id);
    }

    private function reason(array $tag): string
    {
        if (isset($tag['service'])) {
            $services = (array) $tag['service'];

            return \sprintf(1 < \count($services) ? 'none of the services "%s" is there' : 'service "%s" is missing', implode('", "', $services));
        }

        if (isset($tag['package'])) {
            return \sprintf('package "%s" is missing', $tag['package']);
        }

        return \sprintf('class "%s" is missing', $tag['class']);
    }

    /**
     * Drops the definition and whatever aliases it, so that nothing is left dangling.
     */
    private function remove(ContainerBuilder $container, string $id, string $reason): void
    {
        $container->removeDefinition($id);
        $container->log($this, \sprintf('Removed service "%s"; reason: %s.', $id, $reason));

        foreach ($container->getAliases() as $alias => $target) {
            if ($id === (string) $target) {
                $this->remove($container, $alias, \sprintf('it aliases "%s"', $id));
            }
        }

        $container->removeAlias($id);
    }
}
