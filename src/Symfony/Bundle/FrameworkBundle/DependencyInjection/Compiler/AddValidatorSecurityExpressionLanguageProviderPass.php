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

use Symfony\Bundle\FrameworkBundle\Validator\ValidatorSecurityExpressionLanguageProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers security expression functions into the validator expression language
 * when both the Validator and Security components are available.
 */
class AddValidatorSecurityExpressionLanguageProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has('validator.expression_language') || !$container->has('security.authorization_checker')) {
            return;
        }

        $container->register('validator.security_expression_language_provider', ValidatorSecurityExpressionLanguageProvider::class)
            ->setArguments([
                new Reference('security.authorization_checker'),
                new Reference('security.token_storage'),
                new Reference('request_stack'),
            ])
            ->setPublic(false);

        $container->findDefinition('validator.expression_language')
            ->addMethodCall('registerProvider', [new Reference('validator.security_expression_language_provider')]);
    }
}
