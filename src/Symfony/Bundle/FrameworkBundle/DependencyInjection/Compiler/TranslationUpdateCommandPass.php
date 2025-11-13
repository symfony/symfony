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

class TranslationUpdateCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('console.command.translation_extract')) {
            return;
        }

        $translationWriterClass = $container->getParameterBag()->resolveValue($container->findDefinition('translation.writer')->getClass());

        if (!method_exists($translationWriterClass, 'getFormats')) {
            $container->removeDefinition('console.command.translation_extract');
        }
    }
}
