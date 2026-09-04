<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes the storages of `league/flysystem-bundle` reachable from a `...+fly://` DSN.
 *
 * {@see \Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory} resolves the host of
 * its DSN through the services tagged `key_management.flysystem`, while the bundle tags its
 * storages `flysystem.storage`. Neither package knows about the other, so without this the host of
 * every `...+fly://` DSN would name a service the factory cannot see, and each application would
 * write the same handful of lines to say so.
 *
 * The tag copied from is the bundle's own integration point, the one it builds its `$storages`
 * locator with, rather than an internal detail. Should it ever go away, what is left is the
 * unresolved host of a DSN, which the factory reports by name, and the tag placed by hand still
 * works: this pass only ever adds one, and never touches a service already carrying it.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class RegisterFlysystemStoragesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('flysystem.storage') as $id => $tags) {
            $definition = $container->getDefinition($id);

            if ($definition->hasTag('key_management.flysystem')) {
                continue;
            }

            foreach ($tags as $tag) {
                // The bundle names its storages after the service it registers them as, so both
                // sides of the fallback give the same host; the attribute is what the bundle
                // documents, and the id is what any other producer of that tag would have.
                $definition->addTag('key_management.flysystem', ['key' => $tag['storage'] ?? $id]);
            }
        }
    }
}
