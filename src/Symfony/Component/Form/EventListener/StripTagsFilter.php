<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\EventListener;

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Strip tags from incoming input.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class StripTagsFilter implements EventSubscriberInterface
{
    public function filterBoundClientData(FilterDataEvent $event)
    {
        $event->setData(strip_tags($event->getData()));
    }

    public static function getSubscribedEvents()
    {
        return Events::filterBoundDataFromClient;
    }
}