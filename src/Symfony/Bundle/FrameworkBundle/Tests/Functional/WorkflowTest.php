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
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Workflow\Exception\RuntimeException;

class WorkflowTest extends AbstractWebTestCase
{
    public function testThatWorkflowWithSecurityGuardExpressionCanBeAppliedWithSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithSecurityBundle']);

        $token = new AnonymousToken('default', 'anon.');
        static::$container->get('security.token_storage')->setToken($token);

        $marking = static::$container->get(PublicService::class)->apply('test_security_guard_expression');
        $this->assertFalse($marking->has('last'));
    }

    public function testThatWorkflowWithNoSecurityGuardExpressionCanBeAppliedWithSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithSecurityBundle']);

        $token = new AnonymousToken('default', 'anon.');
        static::$container->get('security.token_storage')->setToken($token);

        $marking = static::$container->get(PublicService::class)->apply('test_no_security_guard_expression');
        $this->assertTrue($marking->has('last'));
    }

    public function testThatWorkflowWithSecurityGuardExpressionCanNotBeAppliedWithoutSecurityBundle()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"is_granted" cannot be used as the SecurityBundle is not registered in your application.');

        static::bootKernel(['test_case' => 'WorkflowWithoutSecurityBundle']);

        static::$container->get(PublicService::class)->apply('test_security_guard_expression');
    }

    public function testThatWorkflowWithNoSecurityGuardExpressionCanBeAppliedWithoutSecurityBundle()
    {
        static::bootKernel(['test_case' => 'WorkflowWithoutSecurityBundle']);

        $marking = static::$container->get(PublicService::class)->apply('test_no_security_guard_expression');
        $this->assertTrue($marking->has('last'));
    }
}
