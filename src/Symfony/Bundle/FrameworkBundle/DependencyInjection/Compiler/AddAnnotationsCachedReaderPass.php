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
            $properties = $reader->getProperties();

            if (isset($properties['cacheProviderBackup'])) {
                $provider = $properties['cacheProviderBackup']->getValues()[0];
                unset($properties['cacheProviderBackup']);
                $reader->setProperties($properties);
                $container->set('annotations.cached_reader', null);
                $container->setDefinition('annotations.cached_reader', $reader->replaceArgument(1, $provider));
            }
        }
    }
}
