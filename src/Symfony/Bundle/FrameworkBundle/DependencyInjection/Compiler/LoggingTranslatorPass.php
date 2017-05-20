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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

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

        if ($container->hasParameter('translator.logging') && $container->getParameter('translator.logging')) {
            $translatorAlias = $container->getAlias('translator');
            $definition = $container->getDefinition((string) $translatorAlias);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $translatorAlias));
            }
            if ($r->isSubclassOf(TranslatorInterface::class) && $r->isSubclassOf(TranslatorBagInterface::class)) {
                $container->getDefinition('translator.logging')->setDecoratedService('translator');
                $container->getDefinition('translation.warmer')->replaceArgument(0, new Reference('translator.logging.inner'));
            }
        }
    }
}
