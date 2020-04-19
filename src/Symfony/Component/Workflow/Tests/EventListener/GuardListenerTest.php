<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\Subject;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class GuardListenerTest extends TestCase
{
    private $expressionLanguage;
    private $tokenStorage;
    private $authenticationChecker;
    private $trustResolver;
    private $roleHierarchy;
    private $validator;
    private $listener;
    private $configuration;

    protected function setUp(): void
    {
        $this->configuration = [
            'test_is_granted' => 'is_granted("something")',
            'test_is_valid' => 'is_valid(subject)',
            'test_expression' => [
                new GuardExpression(new Transition('name', 'from', 'to'), '!is_valid(subject)'),
                new GuardExpression(new Transition('name', 'from', 'to'), 'is_valid(subject)'),
            ],
        ];
        $this->expressionLanguage = new ExpressionLanguage();
        $token = new UsernamePasswordToken('username', 'credentials', 'provider', ['ROLE_USER']);
        $this->tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn($token);
        $this->authenticationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $this->trustResolver = $this->getMockBuilder(AuthenticationTrustResolverInterface::class)->getMock();
        $this->validator = $this->getMockBuilder(ValidatorInterface::class)->getMock();
        $this->roleHierarchy = new RoleHierarchy([]);
        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage, $this->tokenStorage, $this->authenticationChecker, $this->trustResolver, $this->roleHierarchy, $this->validator);
    }

    protected function tearDown(): void
    {
        $this->authenticationChecker = null;
        $this->validator = null;
        $this->listener = null;
    }

    public function testWithNotSupportedEvent()
    {
        $event = $this->createEvent();
        $this->configureAuthenticationChecker(false);
        $this->configureValidator(false);

        $this->listener->onTransition($event, 'not supported');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithSecuritySupportedEventAndReject()
    {
        $event = $this->createEvent();
        $this->configureAuthenticationChecker(true, false);

        $this->listener->onTransition($event, 'test_is_granted');

        $this->assertTrue($event->isBlocked());
    }

    public function testWithSecuritySupportedEventAndAccept()
    {
        $event = $this->createEvent();
        $this->configureAuthenticationChecker(true, true);

        $this->listener->onTransition($event, 'test_is_granted');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithValidatorSupportedEventAndReject()
    {
        $event = $this->createEvent();
        $this->configureValidator(true, false);

        $this->listener->onTransition($event, 'test_is_valid');

        $this->assertTrue($event->isBlocked());
    }

    public function testWithValidatorSupportedEventAndAccept()
    {
        $event = $this->createEvent();
        $this->configureValidator(true, true);

        $this->listener->onTransition($event, 'test_is_valid');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithGuardExpressionWithNotSupportedTransition()
    {
        $event = $this->createEvent();
        $this->configureValidator(false);
        $this->listener->onTransition($event, 'test_expression');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithGuardExpressionWithSupportedTransition()
    {
        $event = $this->createEvent($this->configuration['test_expression'][1]->getTransition());
        $this->configureValidator(true, true);
        $this->listener->onTransition($event, 'test_expression');

        $this->assertFalse($event->isBlocked());
    }

    public function testGuardExpressionBlocks()
    {
        $event = $this->createEvent($this->configuration['test_expression'][1]->getTransition());
        $this->configureValidator(true, false);
        $this->listener->onTransition($event, 'test_expression');

        $this->assertTrue($event->isBlocked());
    }

    public function testExceptionIfTheTokenStorageServiceIsNotPresent()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\RuntimeException');
        $this->expectExceptionMessage('"is_granted" cannot be used as the SecurityBundle is not registered in your application.');

        $event = $this->createEvent();

        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage);
        $this->listener->onTransition($event, 'test_is_granted');
    }

    public function testExceptionIfTheAuthorizationCheckerServiceIsNotPresent()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\RuntimeException');
        $this->expectExceptionMessage('"is_granted" cannot be used as the SecurityBundle is not registered in your application.');

        $event = $this->createEvent();

        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage, $this->tokenStorage);
        $this->listener->onTransition($event, 'test_is_granted');
    }

    public function testNoExceptionIfTheAuthenticationTrustResolverServiceIsNotPresent()
    {
        $event = $this->createEvent();
        $this->configureAuthenticationChecker(true, true);

        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage, $this->tokenStorage, $this->authenticationChecker);
        $this->listener->onTransition($event, 'test_is_granted');

        $this->assertFalse($event->isBlocked());
    }

    public function testNoExceptionIfTheRoleHierarchyServiceIsNotPresent()
    {
        $event = $this->createEvent();
        $this->configureAuthenticationChecker(true, true);

        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage, $this->tokenStorage, $this->authenticationChecker, $this->trustResolver);
        $this->listener->onTransition($event, 'test_is_granted');

        $this->assertFalse($event->isBlocked());
    }

    public function testExceptionIfTheValidatorServiceIsNotPresent()
    {
        $this->expectException('Symfony\Component\Workflow\Exception\RuntimeException');
        $this->expectExceptionMessage('"is_valid" cannot be used as the Validator component is not installed.');

        $event = $this->createEvent();

        $this->listener = new GuardListener($this->configuration, $this->expressionLanguage, $this->tokenStorage, $this->authenticationChecker, $this->trustResolver, $this->roleHierarchy);
        $this->listener->onTransition($event, 'test_is_valid');
    }

    private function createEvent(Transition $transition = null)
    {
        $subject = new Subject();
        $transition = $transition ?: new Transition('name', 'from', 'to');

        $workflow = $this->getMockBuilder(WorkflowInterface::class)->getMock();

        return new GuardEvent($subject, new Marking($subject->getMarking() ?? []), $transition, $workflow);
    }

    private function configureAuthenticationChecker($isUsed, $granted = true)
    {
        if (!$isUsed) {
            $this->authenticationChecker
                ->expects($this->never())
                ->method('isGranted')
            ;

            return;
        }

        $this->authenticationChecker
            ->expects($this->once())
            ->method('isGranted')
            ->willReturn($granted)
        ;
    }

    private function configureValidator($isUsed, $valid = true)
    {
        if (!$isUsed) {
            $this->validator
                ->expects($this->never())
                ->method('validate')
            ;

            return;
        }

        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList($valid ? [] : [new ConstraintViolation('a violation', null, [], '', null, '')]))
        ;
    }
}
