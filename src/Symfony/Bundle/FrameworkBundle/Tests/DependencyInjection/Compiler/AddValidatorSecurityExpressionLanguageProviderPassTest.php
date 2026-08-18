<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\AddValidatorSecurityExpressionLanguageProviderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AddValidatorSecurityExpressionLanguageProviderPassTest extends TestCase
{
    public function testProviderIsRegisteredWhenBothServicesExist()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('validator.expression_language', new Definition(\stdClass::class));
        $container->setDefinition('security.authorization_checker', new Definition(\stdClass::class));
        $container->setDefinition('security.token_storage', new Definition(\stdClass::class));
        $container->setDefinition('request_stack', new Definition(\stdClass::class));

        (new AddValidatorSecurityExpressionLanguageProviderPass())->process($container);

        $this->assertTrue($container->hasDefinition('validator.security_expression_language_provider'));
        $this->assertEquals([
            new Reference('security.authorization_checker'),
            new Reference('security.token_storage'),
            new Reference('request_stack'),
        ], $container->getDefinition('validator.security_expression_language_provider')->getArguments());

        $calls = $container->getDefinition('validator.expression_language')->getMethodCalls();
        $this->assertEquals([['registerProvider', [new Reference('validator.security_expression_language_provider')]]], $calls);
    }

    public function testNothingIsRegisteredWithoutSecurity()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('validator.expression_language', new Definition(\stdClass::class));

        (new AddValidatorSecurityExpressionLanguageProviderPass())->process($container);

        $this->assertFalse($container->hasDefinition('validator.security_expression_language_provider'));
        $this->assertSame([], $container->getDefinition('validator.expression_language')->getMethodCalls());
    }

    public function testNothingIsRegisteredWithoutValidator()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('security.authorization_checker', new Definition(\stdClass::class));
        $container->setDefinition('security.token_storage', new Definition(\stdClass::class));

        (new AddValidatorSecurityExpressionLanguageProviderPass())->process($container);

        $this->assertFalse($container->hasDefinition('validator.security_expression_language_provider'));
    }
}
