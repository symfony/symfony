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

use Doctrine\Common\Collections\Collection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

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
        return [
            FormEvents::SUBMIT => [
                ['onSubmit', 5],
            ],
        ];
    }

    public function onSubmit(FormEvent $event)
    {
        $collection = $event->getForm()->getData();
        $data = $event->getData();

        // If all items were removed, call clear which has a higher
        // performance on persistent collections
        if ($collection instanceof Collection && 0 === \count($data)) {
            $collection->clear();
        }
    }
}
