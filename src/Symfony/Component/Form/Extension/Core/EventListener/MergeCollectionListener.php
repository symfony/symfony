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

    /**
     * Whether to search for and use adder and remover methods
     * @var Boolean
     */
    private $useAccessors;

    /**
     * The prefix of the adder method to look for
     * @var string
     */
    private $adderPrefix;

    /**
     * The prefix of the remover method to look for
     * @var string
     */
    private $removerPrefix;

    public function __construct($allowAdd = false, $allowDelete = false, $useAccessors = true, $adderPrefix = 'add', $removerPrefix = 'remove')
    {
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->useAccessors = $useAccessors;
        $this->adderPrefix = $adderPrefix;
        $this->removerPrefix = $removerPrefix;
    }

    static public function getSubscribedEvents()
    {
        return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $originalData = $event->getForm()->getData();

        // If we are not allowed to change anything, return immediately
        if (!$this->allowAdd && !$this->allowDelete) {
            $event->setData($originalData);
            return;
        }

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
        if ($this->useAccessors && is_object($parentData)) {
            $plural = ucfirst($form->getName());
            $singulars = (array) FormUtil::singularify($plural);
            $reflClass = new \ReflectionClass($parentData);

            foreach ($singulars as $singular) {
                $adderName = $this->adderPrefix . $singular;
                $removerName = $this->removerPrefix . $singular;

                if ($this->allowAdd && $reflClass->hasMethod($adderName)) {
                    $adder = $reflClass->getMethod($adderName);

                    if (!$adder->isPublic() || $adder->getNumberOfRequiredParameters() !== 1) {
                        // False alert
                        $adder = null;
                    }
                }

                if ($this->allowDelete && $reflClass->hasMethod($removerName)) {
                    $remover = $reflClass->getMethod($removerName);

                    if (!$remover->isPublic() || $remover->getNumberOfRequiredParameters() !== 1) {
                        // False alert
                        $remover = null;
                    }
                }

                // When we want to both add and delete, we look for an adder and
                // remover with the same name
                if (!($this->allowAdd && !$adder) && !($this->allowDelete && !$remover)) {
                    break;
                }

                // False alert
                $adder = null;
                $remover = null;
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

        if ($adder || $remover) {
            // If methods to add and to remove exist, call them now, if allowed
            if ($remover) {
                foreach ($itemsToDelete as $item) {
                    $remover->invoke($parentData, $item);
                }
            }

            if ($adder) {
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
