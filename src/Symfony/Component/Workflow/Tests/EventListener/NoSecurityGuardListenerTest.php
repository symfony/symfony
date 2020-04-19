<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\NoSecurityGuardListener;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Tests\Subject;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class NoSecurityGuardListenerTest extends TestCase
{
    private $listener;
    private $configuration;

    protected function setUp(): void
    {
        $this->configuration = [
            'test_no_subject_not_blocked' => '1 + 2 === 3',
            'test_no_subject_blocked' => '4 - 1 === 2',
            'test_subject_not_blocked' => 'subject.getMarking() === ["from"]',
            'test_subject_blocked' => 'subject.getMarking() === ["to"]',
            'test_invalid' => 'fn_does_not_exist()',
            'test_security_invalid' => 'is_anonymous()',
            'test_expression' => [
                new GuardExpression(new Transition('name', 'from', 'to'), '2 > 3 || subject.getMarking() === ["to"]'),
                new GuardExpression(new Transition('name', 'from', 'to'), 'subject.getMarking() === ["from"] && "life" in ["life", "universe", "everything"]'),
                new GuardExpression(new Transition('name', 'from', 'to'), 'fn_does_not_exist()'),
                new GuardExpression(new Transition('name', 'from', 'to'), 'is_remember_me()'),
            ],
        ];
        $expressionLanguage = new ExpressionLanguage();
        $this->listener = new NoSecurityGuardListener($this->configuration, $expressionLanguage);
    }

    protected function tearDown(): void
    {
        $this->listener = null;
    }

    public function testWithNotSupportedEvent()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'not supported');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithNoSubjectNotBlocked()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_no_subject_not_blocked');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithNoSubjectBlocked()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_no_subject_blocked');

        $this->assertTrue($event->isBlocked());
    }

    public function testWithSubjectNotBlocked()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_subject_not_blocked');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithSubjectBlocked()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_subject_blocked');

        $this->assertTrue($event->isBlocked());
    }

    public function testWithGuardExpressionWithNotSupportedTransition()
    {
        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_expression');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithGuardExpressionWithSupportedTransition()
    {
        $event = $this->createEvent($this->configuration['test_expression'][1]->getTransition());
        $this->listener->onTransition($event, 'test_expression');

        $this->assertFalse($event->isBlocked());
    }

    public function testGuardExpressionBlocks()
    {
        $event = $this->createEvent($this->configuration['test_expression'][0]->getTransition());
        $this->listener->onTransition($event, 'test_expression');

        $this->assertTrue($event->isBlocked());
    }

    public function testInvalid()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "fn_does_not_exist" does not exist around position 1 for expression `fn_does_not_exist()`.');

        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_invalid');
    }

    public function testGuardExpressionInvalid()
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('The function "fn_does_not_exist" does not exist around position 1 for expression `fn_does_not_exist()`.');

        $event = $this->createEvent($this->configuration['test_expression'][2]->getTransition());
        $this->listener->onTransition($event, 'test_expression');
    }

    public function testSecurityInvalid()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot validate guard expression as the SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');

        $event = $this->createEvent();
        $this->listener->onTransition($event, 'test_security_invalid');
    }

    public function testSecurityGuardExpressionInvalid()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot validate guard expression as the SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');

        $event = $this->createEvent($this->configuration['test_expression'][3]->getTransition());
        $this->listener->onTransition($event, 'test_expression');
    }

    private function createEvent(Transition $transition = null)
    {
        $subject = new Subject(['from']);
        $transition = $transition ?: new Transition('name', 'from', 'to');

        $workflow = $this->getMockBuilder(WorkflowInterface::class)->getMock();

        return new GuardEvent($subject, new Marking($subject->getMarking() ?? []), $transition, $workflow);
    }
}
