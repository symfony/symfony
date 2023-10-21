<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\SchemaListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Events;
use Doctrine\ORM\Tools\ToolEvents;

trigger_deprecation('symfony/doctrine-bridge', '6.3', 'The "%s" class is deprecated. Use "%s" instead.', MessengerTransportDoctrineSchemaSubscriber::class, MessengerTransportDoctrineSchemaListener::class);

/**
 * Automatically adds any required database tables to the Doctrine Schema.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @deprecated since Symfony 6.3, use {@link MessengerTransportDoctrineSchemaListener} instead
 */
final class MessengerTransportDoctrineSchemaSubscriber extends MessengerTransportDoctrineSchemaListener implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        $subscribedEvents = [];

        if (class_exists(ToolEvents::class)) {
            $subscribedEvents[] = ToolEvents::postGenerateSchema;
        }

        if (class_exists(Events::class)) {
            $subscribedEvents[] = Events::onSchemaCreateTable;
        }

        return $subscribedEvents;
    }
}
