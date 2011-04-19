<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\DependencyInjection\Compiler;

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
        $args = $loader->getArguments();

        $args[0]['assertMongoDB'] = 'Symfony\\Bundle\\DoctrineMongoDBBundle\\Validator\\Constraints\\';
        $loader->setArgument(0, $args[0]);
    }
}
