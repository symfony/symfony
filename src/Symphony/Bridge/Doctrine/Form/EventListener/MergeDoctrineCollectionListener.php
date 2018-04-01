<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Doctrine\Form\EventListener;

use Doctrine\Common\Collections\Collection;
use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Merge changes from the request to a Doctrine\Common\Collections\Collection instance.
 *
 * This works with ORM, MongoDB and CouchDB instances of the collection interface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see Collection
 */
class MergeDoctrineCollectionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        // Higher priority than core MergeCollectionListener so that this one
        // is called before
        return array(
            FormEvents::SUBMIT => array(
                array('onSubmit', 5),
            ),
        );
    }

    public function onSubmit(FormEvent $event)
    {
        $collection = $event->getForm()->getData();
        $data = $event->getData();

        // If all items were removed, call clear which has a higher
        // performance on persistent collections
        if ($collection instanceof Collection && 0 === count($data)) {
            $collection->clear();
        }
    }
}
