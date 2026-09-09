<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\Serializer;

/**
 * Reports what cannot work without a serializer, and drops what cannot be wired without one.
 *
 * @internal
 */
class RemoveMissingSerializerDependenciesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('serializer')) {
            return;
        }

        if ($container->hasDefinition('argument_resolver.request_payload')) {
            $container->getDefinition('argument_resolver.request_payload')
                ->setArguments([])
                ->addError('You can neither use "#[MapRequestPayload]" nor "#[MapQueryString]" since the Serializer component is not '
                    .(class_exists(Serializer::class) ? 'enabled. Try setting "serializer.enabled" to true.' : 'installed. Try running "composer require symfony/serializer-pack".')
                )
                ->addTag('container.error')
                ->clearTag('kernel.event_subscriber');
        }

        $container->removeDefinition('console.command.serializer_debug');
    }
}
