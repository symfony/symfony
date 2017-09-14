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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class AddAnnotationsCachedReaderPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // "annotations.cached_reader" is wired late so that any passes using
        // "annotation_reader" at build time don't get any cache
        if ($container->hasDefinition('annotations.cached_reader')) {
            $reader = $container->getDefinition('annotations.cached_reader');
            $tags = $reader->getTags();

            if (isset($tags['annotations.cached_reader'][0]['provider'])) {
                if ($container->hasAlias($provider = $tags['annotations.cached_reader'][0]['provider'])) {
                    $provider = (string) $container->getAlias($provider);
                }
                $container->set('annotations.cached_reader', null);
                $container->setDefinition('annotations.cached_reader', $reader->replaceArgument(1, new Reference($provider)));
            }
        }
    }
}
