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

use Symfony\Component\Form\Util\FormUtil;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MergeCollectionListener implements EventSubscriberInterface
{
    /**
     * Whether elements may be added to the collection
     * @var Boolean
     */
    private $allowAdd;

    /**
     * Whether elements may be removed from the collection
     * @var Boolean
     */
    private $allowDelete;

    public function __construct($allowAdd = false, $allowDelete = false)
    {
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
    }

    static public function getSubscribedEvents()
    {
        return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $originalData = $event->getForm()->getData();
        $form = $event->getForm();
        $data = $event->getData();
        $parentData = $form->hasParent() ? $form->getParent()->getData() : null;
        $adder = null;
        $remover = null;

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if (null !== $originalData && !is_array($originalData) && !($originalData instanceof \Traversable && $originalData instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($originalData, 'array or (\Traversable and \ArrayAccess)');
        }

        // Check if the parent has matching methods to add/remove items
        if (is_object($parentData)) {
            $plural = ucfirst($form->getName());
            $singulars = (array) FormUtil::singularify($plural);
            $reflClass = new \ReflectionClass($parentData);

            foreach ($singulars as $singular) {
                $adderName = 'add' . $singular;
                $removerName = 'remove' . $singular;

                if ($reflClass->hasMethod($adderName) && $reflClass->hasMethod($removerName)) {
                    $adder = $reflClass->getMethod($adderName);
                    $remover = $reflClass->getMethod($removerName);

                    if ($adder->isPublic() && $adder->getNumberOfRequiredParameters() === 1
                        && $remover->isPublic() && $remover->getNumberOfRequiredParameters() === 1) {

                        // We found a public, one-parameter add and remove method
                        break;
                    }

                    // False alert
                    $adder = null;
                    $remover = null;
                }
            }
        }

        // Check which items are in $data that are not in $originalData and
        // vice versa
        $itemsToDelete = array();
        $itemsToAdd = is_object($data) ? clone $data : $data;

        if ($originalData) {
            foreach ($originalData as $originalKey => $originalItem) {
                foreach ($data as $key => $item) {
                    if ($item === $originalItem) {
                        // Item found, next original item
                        unset($itemsToAdd[$key]);
                        continue 2;
                    }
                }

                // Item not found, remember for deletion
                $itemsToDelete[$originalKey] = $originalItem;
            }
        }

        if ($adder && $remover) {
            // If methods to add and to remove exist, call them now, if allowed
            if ($this->allowDelete) {
                foreach ($itemsToDelete as $item) {
                    $remover->invoke($parentData, $item);
                }
            }

            if ($this->allowAdd) {
                foreach ($itemsToAdd as $item) {
                    $adder->invoke($parentData, $item);
                }
            }
        } elseif (!$originalData) {
            // No original data was set. Set it if allowed
            if ($this->allowAdd) {
                $originalData = $data;
            }
        } else {
            // Original data is an array-like structure
            // Add and remove items in the original variable
            if ($this->allowDelete) {
                foreach ($itemsToDelete as $key => $item) {
                    unset($originalData[$key]);
                }
            }

            if ($this->allowAdd) {
                foreach ($itemsToAdd as $key => $item) {
                    if (!isset($originalData[$key])) {
                        $originalData[$key] = $item;
                    } else {
                        $originalData[] = $item;
                    }
                }
            }
        }

        $event->setData($originalData);
    }
}
