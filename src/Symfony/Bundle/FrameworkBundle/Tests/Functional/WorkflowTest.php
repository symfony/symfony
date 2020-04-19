<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Workflow\PublicService;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Workflow\Exception\LogicException;

class WorkflowTest extends AbstractWebTestCase
{
    public function testThatWorkflowWithSecurityGuardExpressionCanBeAppliedWithSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithSecurityBundle']);

        $token = new AnonymousToken('default', 'anon.');
        static::$container->get('security.token_storage')->setToken($token);

        $this->assertFalse(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
        static::$container->get(PublicService::class)->apply('test_security_guard_expression');
        $this->assertFalse(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
    }

    public function testThatWorkflowWithNoSecurityGuardExpressionCanBeAppliedWithSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithSecurityBundle']);

        $token = new AnonymousToken('default', 'anon.');
        static::$container->get('security.token_storage')->setToken($token);

        $this->assertFalse(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
        static::$container->get(PublicService::class)->apply('test_no_security_guard_expression');
        $this->assertTrue(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
    }

    public function testThatWorkflowWithSecurityGuardExpressionCanNotBeAppliedWithoutSecurityBundle()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot validate guard expression as the SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');

        static::bootKernel(['test_case' => 'WorkflowWithoutSecurityBundle']);

        static::$container->get(PublicService::class)->apply('test_security_guard_expression');
    }

    public function testThatWorkflowWithNoSecurityGuardExpressionCanBeAppliedWithoutSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithoutSecurityBundle']);

        $this->assertFalse(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
        static::$container->get(PublicService::class)->apply('test_no_security_guard_expression');
        $this->assertTrue(static::$container->get(PublicService::class)->isApplied('test_security_guard_expression'));
    }

    public function testThatWorkflowWithInvalidSyntaxGuardExpressionCanNotBeAppliedWithSecurityBundle()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "fn_does_not_exist" does not exist around position 1 for expression `fn_does_not_exist()`.');

        static::bootKernel(['test_case' => 'WorkflowWithSecurityBundle']);

        $token = new AnonymousToken('default', 'anon.');
        static::$container->get('security.token_storage')->setToken($token);

        static::$container->get(PublicService::class)->apply('test_invalid_guard_expression');
    }

    public function testThatWorkflowWithInvalidSyntaxGuardExpressionCanNotBeAppliedWithoutSecurityBundle()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "fn_does_not_exist" does not exist around position 1 for expression `fn_does_not_exist()`.');

        static::bootKernel(['test_case' => 'WorkflowWithoutSecurityBundle']);

        static::$container->get(PublicService::class)->apply('test_invalid_guard_expression');
    }
}
