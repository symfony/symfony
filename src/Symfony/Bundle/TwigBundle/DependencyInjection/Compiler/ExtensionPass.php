<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');

            foreach ($container->getExtensions() as $extension) {
                if ($extension instanceof Extension && in_array('Symfony\Bridge\Twig\Extension\FormExtension', $extension->getClassesToCompile(), true)) {
                    throw new \RuntimeException(sprintf(
                        'The "%s" extension cannot add "Symfony\Bridge\Twig\Extension\FormExtension" class to the class cache, because it is necessary to know the path to the original file.',
                        $extension->getAlias()
                    ));
                }
            }

            $reflClass = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
            $container->getDefinition('twig.loader.filesystem')->addMethodCall('addPath', array(dirname(dirname($reflClass->getFileName())).'/Resources/views/Form'));
        }

        if ($container->has('translator')) {
            $container->getDefinition('twig.extension.trans')->addTag('twig.extension');
        }

        if ($container->has('router')) {
            $container->getDefinition('twig.extension.routing')->addTag('twig.extension');
        }

        if ($container->has('fragment.handler')) {
            $container->getDefinition('twig.extension.httpkernel')->addTag('twig.extension');
        }
    }
}
