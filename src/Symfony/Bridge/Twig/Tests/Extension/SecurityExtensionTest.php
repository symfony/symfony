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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityExtensionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClassExistsMock::register(SecurityExtension::class);
    }

    protected function tearDown(): void
    {
        ClassExistsMock::withMockedClasses([FieldVote::class => true, AccessDecision::class => true]);
    }

    #[DataProvider('provideObjectFieldAclCases')]
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
        if (!interface_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        ClassExistsMock::withMockedClasses([FieldVote::class => false]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Passing a $field to the "is_granted()" function requires symfony/acl.');

        $securityExtension = new SecurityExtension($this->createStub(AuthorizationCheckerInterface::class));
        $securityExtension->isGranted('ROLE', 'object', 'bar');
    }

    #[DataProvider('provideObjectFieldAclCases')]
    public function testIsGrantedForUserCreatesFieldVoteObjectWhenFieldNotNull($object, $field, $expectedSubject)
    {
        if (!interface_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $user = new InMemoryUser('john', 'password');
        $securityChecker = $this->createMockAuthorizationChecker();

        $securityExtension = new SecurityExtension($securityChecker);
        $this->assertTrue($securityExtension->isGrantedForUser($user, 'ROLE', $object, $field));
        $this->assertSame($user, $securityChecker->user);
        $this->assertSame('ROLE', $securityChecker->attribute);

        if (null === $field) {
            $this->assertSame($object, $securityChecker->subject);
        } else {
            $this->assertEquals($expectedSubject, $securityChecker->subject);
        }
    }

    public static function provideObjectFieldAclCases()
    {
        return [
            [null, null, null],
            ['object', null, 'object'],
            ['object', '', new FieldVote('object', false)],
            ['object', '0', new FieldVote('object', 0)],
            ['object', '', new FieldVote('object', '')],
            ['object', 'field', new FieldVote('object', 'field')],
        ];
    }

    public function testIsGrantedForUserThrowsWhenFieldNotNullAndFieldVoteClassDoesNotExist()
    {
        if (!interface_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $securityChecker = $this->createMockAuthorizationChecker();

        ClassExistsMock::withMockedClasses([FieldVote::class => false]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Passing a $field to the "is_granted_for_user()" function requires symfony/acl.');

        $securityExtension = new SecurityExtension($securityChecker);
        $securityExtension->isGrantedForUser(new InMemoryUser('john', 'password'), 'ROLE', 'object', 'bar');
    }

    public function testAccessDecision()
    {
        if (!class_exists(AccessDecision::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $securityChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $securityChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('ROLE', 'object', $this->isInstanceOf(AccessDecision::class))
            ->willReturnCallback(static function ($attribute, $subject, $accessDecision) {
                $accessDecision->isGranted = true;

                return true;
            });

        $securityExtension = new SecurityExtension($securityChecker);
        $accessDecision = $securityExtension->getAccessDecision('ROLE', 'object');

        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertTrue($accessDecision->isGranted);
    }

    public function testAccessDecisionWithField()
    {
        if (!class_exists(AccessDecision::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $securityChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $securityChecker
            ->expects($this->once())
            ->method('isGranted')
            ->with('ROLE', $this->isInstanceOf(FieldVote::class), $this->isInstanceOf(AccessDecision::class))
            ->willReturnCallback(static function ($attribute, $subject, $accessDecision) {
                $accessDecision->isGranted = false;

                return false;
            });

        $securityExtension = new SecurityExtension($securityChecker);
        $accessDecision = $securityExtension->getAccessDecision('ROLE', 'object', 'field');

        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertFalse($accessDecision->isGranted);
    }

    public function testAccessDecisionThrowsWhenAccessDecisionClassDoesNotExist()
    {
        ClassExistsMock::withMockedClasses([AccessDecision::class => false]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Using the "access_decision()" function requires symfony/security-core >= 7.3. Try running "composer update symfony/security-core".');

        $securityExtension = new SecurityExtension($this->createStub(AuthorizationCheckerInterface::class));
        $securityExtension->getAccessDecision('ROLE', 'object');
    }

    public function testAccessDecisionForUser()
    {
        if (!interface_exists(UserAuthorizationCheckerInterface::class) || !class_exists(AccessDecision::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $user = new InMemoryUser('john', 'password');
        $securityChecker = $this->createMockAuthorizationChecker();

        $securityExtension = new SecurityExtension($securityChecker);
        $accessDecision = $securityExtension->getAccessDecisionForUser($user, 'ROLE', 'object');

        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertTrue($accessDecision->isGranted);
        $this->assertSame($user, $securityChecker->user);
        $this->assertSame('ROLE', $securityChecker->attribute);
        $this->assertSame('object', $securityChecker->subject);
    }

    public function testAccessDecisionForUserWithField()
    {
        if (!interface_exists(UserAuthorizationCheckerInterface::class) || !class_exists(AccessDecision::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        $user = new InMemoryUser('john', 'password');
        $securityChecker = $this->createMockAuthorizationChecker();

        $securityExtension = new SecurityExtension($securityChecker);
        $accessDecision = $securityExtension->getAccessDecisionForUser($user, 'ROLE', 'object', 'field');

        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertTrue($accessDecision->isGranted);
        $this->assertSame($user, $securityChecker->user);
        $this->assertSame('ROLE', $securityChecker->attribute);
        $this->assertEquals(new FieldVote('object', 'field'), $securityChecker->subject);
    }

    public function testAccessDecisionForUserThrowsWhenAccessDecisionClassDoesNotExist()
    {
        if (!interface_exists(UserAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 7.3 or superior.');
        }

        ClassExistsMock::withMockedClasses([AccessDecision::class => false]);

        $securityChecker = $this->createMockAuthorizationChecker();
        $securityExtension = new SecurityExtension($securityChecker);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Using the "access_decision_for_user()" function requires symfony/security-core >= 7.3. Try running "composer update symfony/security-core".');

        $securityExtension->getAccessDecisionForUser(new InMemoryUser('john', 'password'), 'ROLE', 'object');
    }

    private function createMockAuthorizationChecker(): AuthorizationCheckerInterface&UserAuthorizationCheckerInterface
    {
        return new class implements AuthorizationCheckerInterface, UserAuthorizationCheckerInterface {
            public UserInterface $user;
            public mixed $attribute;
            public mixed $subject;

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                throw new \BadMethodCallException('This method should not be called.');
            }

            public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->user = $user;
                $this->attribute = $attribute;
                $this->subject = $subject;

                if ($accessDecision) {
                    $accessDecision->isGranted = true;
                }

                return true;
            }
        };
    }
}
