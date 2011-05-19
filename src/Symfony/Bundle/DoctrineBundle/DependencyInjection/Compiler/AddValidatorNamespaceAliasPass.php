<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class AddValidatorNamespaceAliasPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('validator.mapping.loader.annotation_loader')) {
            return;
        }

        $loader = $container->getDefinition('validator.mapping.loader.annotation_loader');
        $args = $container->getParameterBag()->resolveValue($loader->getArguments());

        $args[0]['assertORM'] = 'Symfony\\Bridge\\Doctrine\\Validator\\Constraints\\';
        $loader->replaceArgument(0, $args[0]);
    }
}