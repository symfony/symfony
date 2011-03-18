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

class MergeCollectionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return Events::filterBoundData;
    }

    public function filterBoundData(FilterDataEvent $event)
    {
        $collection = $event->getField()->getData();
        $data = $event->getData();

        if (!$collection) {
            $collection = $data;
        } else if (count($data) === 0) {
            $collection->clear();
        } else {
            // merge $data into $collection
            foreach ($collection as $entity) {
                if (!$data->contains($entity)) {
                    $collection->removeElement($entity);
                } else {
                    $data->removeElement($entity);
                }
            }

            foreach ($data as $entity) {
                $collection->add($entity);
            }
        }

        $event->setData($collection);
    }
}