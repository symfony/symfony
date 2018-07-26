<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Adds all services with the tags "serializer.encoder" and "serializer.normalizer" as
 * encoders and normalizers to the "serializer" service.
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class SerializerPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    private $serializerService;
    private $normalizerTag;
    private $encoderTag;

    public function __construct($serializerService = 'serializer', $normalizerTag = 'serializer.normalizer', $encoderTag = 'serializer.encoder')
    {
        $this->serializerService = $serializerService;
        $this->normalizerTag = $normalizerTag;
        $this->encoderTag = $encoderTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->serializerService)) {
            return;
        }

        if (!$normalizers = $this->findAndSortTaggedServices($this->normalizerTag, $container)) {
            throw new RuntimeException(sprintf('You must tag at least one service as "%s" to use the "%s" service.', $this->normalizerTag, $this->serializerService));
        }

        $serializerDefinition = $container->getDefinition($this->serializerService);
        $serializerDefinition->replaceArgument(0, $normalizers);

        if (!$encoders = $this->findAndSortTaggedServices($this->encoderTag, $container)) {
            throw new RuntimeException(sprintf('You must tag at least one service as "%s" to use the "%s" service.', $this->encoderTag, $this->serializerService));
        }

        $serializerDefinition->replaceArgument(1, $encoders);
    }
}
