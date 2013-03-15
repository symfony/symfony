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

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');
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
