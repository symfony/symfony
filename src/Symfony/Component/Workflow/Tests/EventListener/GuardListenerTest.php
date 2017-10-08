<?php

namespace Symfony\Component\Workflow\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Workflow\EventListener\ExpressionLanguage;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class GuardListenerTest extends TestCase
{
    private $tokenStorage;
    private $listener;

    protected function setUp()
    {
        $configuration = array(
            'event_name_a' => 'true',
            'event_name_b' => 'false',
        );

        $expressionLanguage = new ExpressionLanguage();
        $this->tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $authenticationChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $trustResolver = $this->getMockBuilder(AuthenticationTrustResolverInterface::class)->getMock();

        $this->listener = new GuardListener($configuration, $expressionLanguage, $this->tokenStorage, $authenticationChecker, $trustResolver);
    }

    protected function tearDown()
    {
        $this->listener = null;
    }

    public function testWithNotSupportedEvent()
    {
        $event = $this->createEvent();
        $this->configureTokenStorage(false);

        $this->listener->onTransition($event, 'not supported');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithSupportedEventAndReject()
    {
        $event = $this->createEvent();
        $this->configureTokenStorage(true);

        $this->listener->onTransition($event, 'event_name_a');

        $this->assertFalse($event->isBlocked());
    }

    public function testWithSupportedEventAndAccept()
    {
        $event = $this->createEvent();
        $this->configureTokenStorage(true);

        $this->listener->onTransition($event, 'event_name_b');

        $this->assertTrue($event->isBlocked());
    }

    /**
     * @expectedException \Symfony\Component\Workflow\Exception\InvalidTokenConfigurationException
     * @expectedExceptionMessage There are no tokens available for workflow unnamed.
     */
    public function testWithNoTokensInTokenStorage()
    {
        $event = $this->createEvent();
        $this->tokenStorage->setToken(null);

        $this->listener->onTransition($event, 'event_name_a');
    }

    private function createEvent()
    {
        $subject = new \stdClass();
        $subject->marking = new Marking();
        $transition = new Transition('name', 'from', 'to');

        return new GuardEvent($subject, $subject->marking, $transition);
    }

    private function configureTokenStorage($hasUser)
    {
        if (!$hasUser) {
            $this->tokenStorage
                ->expects($this->never())
                ->method('getToken')
            ;

            return;
        }

        $token = $this->getMockBuilder(TokenInterface::class)->getMock();
        $token
            ->expects($this->once())
            ->method('getRoles')
            ->willReturn(array(new Role('ROLE_ADMIN')))
        ;

        $this->tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->willReturn($token)
        ;
    }
}
