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
    // Keep BC. To be removed in 4.0
    private $bc = true;
    private $bcLayer = false;

    public static function getSubscribedEvents()
    {
        // Higher priority than core MergeCollectionListener so that this one
        // is called before
        return [
            FormEvents::SUBMIT => [
                ['onBind', 10], // deprecated
                ['onSubmit', 5],
            ],
        ];
    }

    public function onSubmit(FormEvent $event)
    {
        if ($this->bc) {
            // onBind() has been overridden from a child class
            @trigger_error('The onBind() method is deprecated since Symfony 3.1 and will be removed in 4.0. Use the onSubmit() method instead.', \E_USER_DEPRECATED);

            if (!$this->bcLayer) {
                // If parent::onBind() has not been called, then logic has been executed
                return;
            }
        }

        $collection = $event->getForm()->getData();
        $data = $event->getData();

        // If all items were removed, call clear which has a higher
        // performance on persistent collections
        if ($collection instanceof Collection && 0 === \count($data)) {
            $collection->clear();
        }
    }

    /**
     * Alias of {@link onSubmit()}.
     *
     * @deprecated since version 3.1, to be removed in 4.0.
     *             Use {@link onSubmit()} instead.
     */
    public function onBind(FormEvent $event)
    {
        if (__CLASS__ === static::class) {
            $this->bc = false;
        } else {
            // parent::onBind() has been called
            $this->bcLayer = true;
        }
    }
}
