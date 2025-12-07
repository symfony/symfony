<?php

namespace Symfony\Component\AccessControl\Tests;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\Event\AccessDecisionEvent;
use Symfony\Component\AccessControl\Event\VoteEvent;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;

final class EventsTest extends StrategyTestCase
{
    public function testDecide(): void
    {
        // Arrange
        $accessRequest = new AccessRequest(new NullToken(), 'PUBLIC_ACCESS');

        // Act
        $this->getAccessControlManager()->decide($accessRequest);

        // Assert
        $events = $this->getEventDispatcher()->events;
        $this->assertCount(2, $events);
        $this->assertInstanceOf(VoteEvent::class, $events[0]);
        $this->assertInstanceOf(AccessDecisionEvent::class, $events[1]);
    }
}
