<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\EventListener;

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Merge changes from the request to a Doctrine\Common\Collections\Collection instance.
 *
 * This works with ORM, MongoDB and CouchDB instances of the collection interface.
 *
 * @see Doctrine\Common\Collections\Collection
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class MergeCollectionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return Events::onBindNormData;
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $collection = $event->getForm()->getData();
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