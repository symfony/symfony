<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Trims string data
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TrimListener implements EventSubscriberInterface
{
    public function onBindClientData(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (is_string($data)) {
            $event->setData(trim($data));
        }
    }

    public static function getSubscribedEvents()
    {
        return Events::onBindClientData;
    }
}