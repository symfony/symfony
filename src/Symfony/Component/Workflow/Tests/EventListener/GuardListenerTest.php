<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class GuardListenerTest extends TestCase
{
    private $authenticationChecker;
    private $validator;
    private $listener;
    private $configuration;

    protected function setUp()
    {
        $this->configuration = array(
            'test_is_granted' => 'is_granted("something")',
            'test_is_valid' => 'is_valid(subject)',
            'test_expression' => array(
                new GuardExpression(new Transition('name', 'from', 'to'), '!is_valid(subject)'),
                new GuardExpression(new Transition('name', 'from', 'to'), 'is_valid(subject)'),
            ),
        );
        $expressionLanguage = new ExpressionLanguage();
        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token->expects($this->any())->method('getRoles')->willReturn(array(new Role('ROLE_USER')));
        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->expects($this->any())->method('getToken')->willReturn($token);
        $this->authenticationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $trustResolver = $this->getMockBuilder(AuthenticationTrustResolverInterface::class)->getMock();
        $this->validator = $this->getMockBuilder(ValidatorInterface::class)->getMock();
        $this->listener = new GuardListener($this->configuration, $expressionLanguage, $tokenStorage, $this->authenticationChecker, $trustResolver, null, $this->validator);
    }

    protected function tearDown()
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

    private function createEvent(Transition $transition = null)
    {
        $subject = new \stdClass();
        $subject->marking = new Marking();
        $transition = $transition ?: new Transition('name', 'from', 'to');

        return new GuardEvent($subject, $subject->marking, $transition);
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
            ->willReturn($valid ? array() : array('a violation'))
        ;
    }
}
