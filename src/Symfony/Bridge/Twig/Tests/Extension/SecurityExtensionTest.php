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
use Symfony\Component\Security\Core\Authorization\GuestAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\UserAuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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

    public function testImpersonationFormsAreEmptyWithoutAnImpersonateUrlGenerator()
    {
        $extension = new SecurityExtension();

        $this->assertSame(['action' => '', 'fields' => [], 'csrf_field' => null], $extension->getImpersonateForm('kuba'));
        $this->assertSame(['action' => '', 'fields' => [], 'csrf_field' => null], $extension->getImpersonateExitForm());
    }

    public function testImpersonationButtonsAreEmptyWithoutAnImpersonateUrlGenerator()
    {
        $this->assertSame('', $this->render('{{ impersonation_button("Impersonate", "kuba") }}{{ impersonation_exit_button() }}', new SecurityExtension()));
    }

    public function testImpersonationButtonRendersTheImpersonationForm()
    {
        $generator = $this->createMock(ImpersonateUrlGenerator::class);
        $generator->expects($this->once())
            ->method('generateImpersonationForm')->with('kuba', '/dashboard')
            ->willReturn(['action' => '/switch-user', 'fields' => ['_switch_user' => 'kuba', '_csrf_token' => 'T0K3N', '_target_path' => '/dashboard'], 'csrf_field' => '_csrf_token']);

        $this->assertSame(
            '<form method="post" action="/switch-user" class="inline"><input type="hidden" name="_switch_user" value="kuba"><input type="hidden" name="_csrf_token" value="T0K3N" data-controller="csrf-protection"><input type="hidden" name="_target_path" value="/dashboard"><button type="submit" class="link">Impersonate &lt;kuba&gt;</button></form>',
            $this->render('{{ impersonation_button("Impersonate <kuba>", "kuba", target_uri: "/dashboard", attr: {class: "link"}, form_attr: {class: "inline"}) }}', new SecurityExtension(null, $generator, ['data-controller' => 'csrf-protection'])),
        );
    }

    public function testImpersonationExitButtonRendersTheExitForm()
    {
        $generator = $this->createMock(ImpersonateUrlGenerator::class);
        $generator->expects($this->once())
            ->method('generateExitForm')->with('/admin/users')
            ->willReturn(['action' => '/switch-user', 'fields' => ['_switch_user' => '_exit', '_target_path' => '/admin/users'], 'csrf_field' => null]);

        $this->assertSame(
            '<form method="post" action="/switch-user"><input type="hidden" name="_switch_user" value="_exit"><input type="hidden" name="_target_path" value="/admin/users"><button type="submit">Exit impersonation</button></form>',
            $this->render('{{ impersonation_exit_button(target_uri: "/admin/users") }}', new SecurityExtension(null, $generator, ['data-controller' => 'csrf-protection'])),
        );
    }

    public function testImpersonationExitButtonIsEmptyWhenNotImpersonating()
    {
        $generator = $this->createStub(ImpersonateUrlGenerator::class);
        $generator->method('generateExitForm')->willReturn(['action' => '', 'fields' => [], 'csrf_field' => null]);

        $this->assertSame('', $this->render('{{ impersonation_exit_button("Back") }}', new SecurityExtension(null, $generator)));
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

    #[DataProvider('provideObjectFieldAclCases')]
    public function testIsGrantedForGuestCreatesFieldVoteObjectWhenFieldNotNull($object, $field, $expectedSubject)
    {
        if (!interface_exists(GuestAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 8.2 or superior.');
        }

        $securityChecker = $this->createMockAuthorizationChecker();

        $securityExtension = new SecurityExtension($securityChecker);
        $this->assertTrue($securityExtension->isGrantedForUser(null, 'ROLE', $object, $field));
        $this->assertNull($securityChecker->user);
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

    public function testAccessDecisionForGuestWithField()
    {
        if (!interface_exists(GuestAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 8.2 or superior.');
        }

        $securityChecker = $this->createMockAuthorizationChecker();

        $securityExtension = new SecurityExtension($securityChecker);
        $accessDecision = $securityExtension->getAccessDecisionForUser(null, 'ROLE', 'object', 'field');

        $this->assertInstanceOf(AccessDecision::class, $accessDecision);
        $this->assertTrue($accessDecision->isGranted);
        $this->assertNull($securityChecker->user);
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

    public function testFunctionsForUserAreRegisteredWithAGuestChecker()
    {
        if (!interface_exists(GuestAuthorizationCheckerInterface::class)) {
            $this->markTestSkipped('This test requires symfony/security-core 8.2 or superior.');
        }

        $securityChecker = new class implements AuthorizationCheckerInterface, GuestAuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                throw new \BadMethodCallException('This method should not be called.');
            }

            public function isGrantedForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                throw new \BadMethodCallException('This method should not be called.');
            }
        };

        $functions = array_map(static fn ($function) => $function->getName(), (new SecurityExtension($securityChecker))->getFunctions());

        $this->assertContains('is_granted_for_user', $functions);
        $this->assertContains('access_decision_for_user', $functions);
    }

    private function render(string $template, SecurityExtension $extension): string
    {
        $twig = new Environment(new ArrayLoader(['template' => $template]), ['strict_variables' => true]);
        $twig->addExtension($extension);

        return $twig->render('template');
    }

    private function createMockAuthorizationChecker(): MockAuthorizationChecker
    {
        if (interface_exists(GuestAuthorizationCheckerInterface::class)) {
            return new class extends MockAuthorizationChecker implements GuestAuthorizationCheckerInterface {};
        }

        return new class extends MockAuthorizationChecker {};
    }
}

class MockAuthorizationChecker implements AuthorizationCheckerInterface, UserAuthorizationCheckerInterface
{
    public ?UserInterface $user;
    public mixed $attribute;
    public mixed $subject;

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        throw new \BadMethodCallException('This method should not be called.');
    }

    public function isGrantedForUser(?UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $this->user = $user;
        $this->attribute = $attribute;
        $this->subject = $subject;

        if ($accessDecision) {
            $accessDecision->isGranted = true;
        }

        return true;
    }
}
