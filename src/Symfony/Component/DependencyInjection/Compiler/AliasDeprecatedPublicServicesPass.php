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
use Symfony\Component\DependencyInjection\Reference;

final class AliasDeprecatedPublicServicesPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    private array $aliases = [];

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('container.private') as $id => $tags) {
            if (null === $package = $tags[0]['package'] ?? null) {
                throw new InvalidArgumentException(\sprintf('The "package" attribute is mandatory for the "container.private" tag on the "%s" service.', $id));
            }

            if (null === $version = $tags[0]['version'] ?? null) {
                throw new InvalidArgumentException(\sprintf('The "version" attribute is mandatory for the "container.private" tag on the "%s" service.', $id));
            }

            $definition = $container->getDefinition($id);
            if (!$definition->isPublic() || $definition->isPrivate()) {
                continue;
            }

            $container
                ->setAlias($id, $aliasId = '.container.private.'.$id)
                ->setPublic(true)
                ->setDeprecated($package, $version, 'Accessing the "%alias_id%" service directly from the container is deprecated, use dependency injection instead.');

            $container->setDefinition($aliasId, $definition);

            $this->aliases[$id] = $aliasId;
        }

        parent::process($container);
    }

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        if ($value instanceof Reference && isset($this->aliases[$id = (string) $value])) {
            return new Reference($this->aliases[$id], $value->getInvalidBehavior());
        }

        return parent::processValue($value, $isRoot);
    }
}
