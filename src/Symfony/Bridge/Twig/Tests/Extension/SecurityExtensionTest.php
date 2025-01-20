<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityExtensionTest extends TestCase
{
    /**
     * @dataProvider provideObjectFieldAclCases
     */
    public function testIsGrantedCreatesFieldVoteObjectWhenFieldNotNull($object, $field, $expectedSubject)
    {
        $securityChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $securityChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('ROLE', $expectedSubject)
            ->willReturn(true);

        $securityExtension = new SecurityExtension($securityChecker);
        $this->assertTrue($securityExtension->isGranted('ROLE', $object, $field));
    }

    public function testIsGrantedThrowsWhenFieldNotNullAndFieldVoteClassDoesNotExist()
    {
        if (!class_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $securityChecker = $this->createMock(AuthorizationCheckerInterface::class);

        ClassExistsMock::register(SecurityExtension::class);
        ClassExistsMock::withMockedClasses([FieldVote::class => false]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('Passing a $field to the "is_granted()" function requires symfony/acl.');

        $securityExtension = new SecurityExtension($securityChecker);
        $securityExtension->isGranted('ROLE', 'object', 'bar');
    }

    /**
     * @dataProvider provideObjectFieldAclCases
     */
    public function testIsGrantedForUserCreatesFieldVoteObjectWhenFieldNotNull($object, $field, $expectedSubject)
    {
        if (!class_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $user = $this->createMock(UserInterface::class);
        $userSecurityChecker = $this->createMock(UserAuthorizationCheckerInterface::class);
        $userSecurityChecker
            ->expects($this->once())
            ->method('isGrantedForUser')
            ->with($user, 'ROLE', $expectedSubject)
            ->willReturn(true);

        $securityExtension = new SecurityExtension(null, null, $userSecurityChecker);
        $this->assertTrue($securityExtension->isGrantedForUser($user, 'ROLE', $object, $field));
    }

    public function testIsGrantedForUserThrowsWhenFieldNotNullAndFieldVoteClassDoesNotExist()
    {
        if (!class_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $securityChecker = $this->createMock(UserAuthorizationCheckerInterface::class);

        ClassExistsMock::register(SecurityExtension::class);
        ClassExistsMock::withMockedClasses([FieldVote::class => false]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('Passing a $field to the "is_granted_for_user()" function requires symfony/acl.');

        $securityExtension = new SecurityExtension(null, null, $securityChecker);
        $securityExtension->isGrantedForUser($this->createMock(UserInterface::class), 'object', 'bar');
    }

    public static function provideObjectFieldAclCases()
    {
        return [
            [null, null, null],
            ['object', null, 'object'],
            ['object', false, new FieldVote('object', false)],
            ['object', 0, new FieldVote('object', 0)],
            ['object', '', new FieldVote('object', '')],
            ['object', 'field', new FieldVote('object', 'field')],
        ];
    }
}
