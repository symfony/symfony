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
 * Parses expressions used in the security access_control configuration to make sure they are dumped as SerializedParsedExpression
 * This compiler pass must be registered after AddExpressionLanguageProvidersPass so custom functions can also be parsed.
 *
 * @author David Maicher <mail@dmaicher.de>
 */
class ParseSecurityExpressionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('security.expression_language')) {
            return;
        }

        $expressionLanguage = $container->get('security.expression_language');

        foreach ($container->findTaggedServiceIds('security.expression') as $id => $attributes) {
            $definition = $container->getDefinition($id);
            $definition
                ->setClass('Symfony\Component\ExpressionLanguage\SerializedParsedExpression')
                ->addArgument(serialize($expressionLanguage->parse($definition->getArgument(0), array('token', 'user', 'object', 'roles', 'request', 'trust_resolver'))->getNodes()))
                ->setTags(array());
        }
    }
}
