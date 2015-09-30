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
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class LoggingTranslatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasAlias('logger') || !$container->hasAlias('translator')) {
            return;
        }

        // skip if the symfony/translation version is lower than 2.6
        if (!interface_exists('Symfony\Component\Translation\TranslatorBagInterface')) {
            return;
        }

        if ($container->hasParameter('translator.logging') && $container->getParameter('translator.logging')) {
            $translatorAlias = $container->getAlias('translator');
            $definition = $container->getDefinition((string) $translatorAlias);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            $refClass = new \ReflectionClass($class);
            if ($refClass->implementsInterface('Symfony\Component\Translation\TranslatorInterface') && $refClass->implementsInterface('Symfony\Component\Translation\TranslatorBagInterface')) {
                $container->getDefinition('translator.logging')->setDecoratedService('translator');
                $container->getDefinition('translation.warmer')->replaceArgument(0, new Reference('translator.logging.inner'));
            }
        }
    }
}
