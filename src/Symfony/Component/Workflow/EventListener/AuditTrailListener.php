<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;

class AuditTrailListener implements EventSubscriberInterface
{
    public function onEnter(Event $event)
    {
// FIXME: object "identity", timestamp, who, ...
error_log('entering "'.$event->getState().'" generic for object of class '.get_class($event->getObject()));
    }

    public function onLeave(Event $event)
    {
error_log('leaving "'.$event->getState().'" generic for object of class '.get_class($event->getObject()));
    }

    public function onTransition(Event $event)
    {
error_log('transition "'.$event->getState().'" generic for object of class '.get_class($event->getObject()));
    }

    public static function getSubscribedEvents()
    {
        return array(
// FIXME: add a way to listen to workflow.XXX.*
            'workflow.transition' => array('onTransition'),
            'workflow.leave' => array('onLeave'),
            'workflow.enter' => array('onEnter'),
        );
    }
}
