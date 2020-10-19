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

use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\AddExpressionLanguageProvidersPass as SecurityExpressionLanguageProvidersPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the expression language providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddExpressionLanguageProvidersPass implements CompilerPassInterface
{
    private $handleSecurityLanguageProviders;

    public function __construct(bool $handleSecurityLanguageProviders = true)
    {
        if ($handleSecurityLanguageProviders) {
            @trigger_error(sprintf('Registering services tagged "security.expression_language_provider" with "%s" is deprecated since Symfony 4.2, use the "%s" instead.', __CLASS__, SecurityExpressionLanguageProvidersPass::class), \E_USER_DEPRECATED);
        }

        $this->handleSecurityLanguageProviders = $handleSecurityLanguageProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // routing
        if ($container->has('router')) {
            $definition = $container->findDefinition('router');
            foreach ($container->findTaggedServiceIds('routing.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', [new Reference($id)]);
            }
        }

        // security
        if ($this->handleSecurityLanguageProviders && $container->has('security.expression_language')) {
            $definition = $container->findDefinition('security.expression_language');
            foreach ($container->findTaggedServiceIds('security.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('registerProvider', [new Reference($id)]);
            }
        }
    }
}
