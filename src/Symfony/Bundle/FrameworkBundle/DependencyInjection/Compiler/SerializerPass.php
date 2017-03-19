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

use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Adds all services with the tags "serializer.encoder" and "serializer.normalizer" as
 * encoders and normalizers to the Serializer service.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        // Looks for all the services tagged "serializer.normalizer" and adds them to the Serializer service
        $normalizers = $this->findAndSortTaggedServices('serializer.normalizer', $container);

        if (empty($normalizers)) {
            throw new RuntimeException('You must tag at least one service as "serializer.normalizer" to use the Serializer service');
        }
        $container->getDefinition('serializer')->replaceArgument(0, $normalizers);

        // Looks for all the services tagged "serializer.encoders" and adds them to the Serializer service
        $encoders = $this->findAndSortTaggedServices('serializer.encoder', $container);
        if (empty($encoders)) {
            throw new RuntimeException('You must tag at least one service as "serializer.encoder" to use the Serializer service');
        }
        $container->getDefinition('serializer')->replaceArgument(1, $encoders);
    }
}
