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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableDenormalizer;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableHybridNormalizer;
use Symfony\Component\Serializer\Debug\Normalizer\TraceableNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SerializerDebugPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('serializer')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('serializer.normalizer') as $id => $tags) {
            $this->decorateNormalizer($id, $container);
        }
    }

    private function decorateNormalizer(string $id, ContainerBuilder $container): void
    {
        $aliasName = 'debug.'.$id;

        $normalizerDef = $container->getDefinition($id);
        $normalizerClass = $normalizerDef->getClass();

        if (!$normalizerRef = $container->getReflectionClass($normalizerClass)) {
            throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $normalizerClass, $id));
        }

        $isNormalizer = $normalizerRef->implementsInterface(NormalizerInterface::class);
        $isDenormalizer = $normalizerRef->implementsInterface(DenormalizerInterface::class);

        /*
         * We must decorate each type of normalizer with a specific decorator, since the serializer behaves
         * differently depending of instanceof checks against the used normalizer.
         * Therefore we cannot decorate all normalizers equally.
         */
        if ($isNormalizer && $isDenormalizer) {
            $decoratorClass = TraceableHybridNormalizer::class;
        } elseif ($isNormalizer) {
            $decoratorClass = TraceableNormalizer::class;
        } elseif ($isDenormalizer) {
            $decoratorClass = TraceableDenormalizer::class;
        } else {
            throw new RuntimeException(sprintf('Normalizer with id "%s" neither implements NormalizerInterface nor DenormalizerInterface!', $id));
        }

        $decoratorDef = (new Definition($decoratorClass))
            ->setArguments([$normalizerDef])
            ->addTag('debug.normalizer')
            ->setDecoratedService($id)
            ->setAutowired(true)
        ;

        $container->setDefinition($aliasName, $decoratorDef);
    }
}
