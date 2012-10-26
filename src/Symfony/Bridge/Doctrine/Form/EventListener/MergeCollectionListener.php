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

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Merge changes from the request to a Doctrine\Common\Collections\Collection instance.
 *
 * This works with ORM, MongoDB and CouchDB instances of the collection interface.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 *
 * @see    Doctrine\Common\Collections\Collection
 */
class MergeCollectionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $collection = $event->getForm()->getNormData();
        $data = $event->getData();

        if (!$collection) {
            $collection = $data;
        } elseif (count($data) === 0) {
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
