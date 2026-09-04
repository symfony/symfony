<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class ExpressionLanguageProviderTest extends AbstractWebTestCase
{
    public function testTheSecurityFunctionsAreEvaluatedThroughTheServices()
    {
        $container = $this->bootTestKernel();

        $user = new InMemoryUser('chalasr', 'the-password', ['ROLE_FOO']);
        $container->get('test.request_stack')->push(new Request());
        $container->get('test.security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_FOO']));

        $expressionLanguage = $this->createExpressionLanguage($container);

        $this->assertSame($user, $expressionLanguage->evaluate('current_user()'));
        $this->assertTrue($expressionLanguage->evaluate('is_granted("ROLE_FOO")'));
        $this->assertFalse($expressionLanguage->evaluate('is_granted("ROLE_BAR")'));
        $this->assertTrue($expressionLanguage->evaluate('is_authenticated()'));
    }

    public function testTheSecurityFunctionsThrowOutsideOfARequest()
    {
        $expressionLanguage = $this->createExpressionLanguage($this->bootTestKernel());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "current_user()" function cannot be evaluated outside of a request.');

        $expressionLanguage->evaluate('current_user()');
    }

    private function createExpressionLanguage(ContainerInterface $container): ExpressionLanguage
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider($container->get('test.security.expression_language_provider'));

        return $expressionLanguage;
    }

    private function bootTestKernel(): ContainerInterface
    {
        $kernel = self::createKernel(['test_case' => 'ExpressionLanguageProvider', 'root_config' => 'config.yml']);
        $kernel->boot();

        return $kernel->getContainer();
    }
}
