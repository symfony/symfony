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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class ClassesToCompilePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $classes = array_merge($container->getParameter('kernel.bundles'), array(
            'Symfony\Component\Form\Form',
            'Symfony\Component\Form\FormInterface',
            'Symfony\Component\Validator\Validator',
            'Symfony\Component\Security\Core\Exception\AuthenticationException',
        ));

        foreach ($container->getExtensions() as $extension) {
            if (!$extension instanceof Extension) {
                continue;
            }

            $matchClasses = array_intersect($classes, $extension->getClassesToCompile());
            if (!empty($matchClasses)) {
                throw new \RuntimeException(sprintf(
                    'The "%s" extension cannot add "%s" class%s to the class cache, because it is necessary to know the path to the original file.',
                    $extension->getAlias(),
                    implode('", "', $matchClasses),
                    count($matchClasses) > 1 ? 'es' : ''
                ));
            }
        }
    }
}
