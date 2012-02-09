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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Util\FormUtil;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class MergeCollectionListener implements EventSubscriberInterface
{
    /**
     * Strategy for merging the new collection into the old collection
     *
     * @var integer
     */
    const MERGE_NORMAL = 1;

    /**
     * Strategy for calling add/remove methods on the parent data for all
     * new/removed elements in the new collection
     *
     * @var integer
     */
    const MERGE_INTO_PARENT = 2;

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
    private $mergeStrategy;

    /**
     * The name of the adder method to look for
     * @var string
     */
    private $addMethod;

    /**
     * The name of the remover method to look for
     * @var string
     */
    private $removeMethod;

    /**
     * A copy of the data before starting binding for this form
     * @var mixed
     */
    private $dataSnapshot;

    /**
     * Creates a new listener.
     *
     * @param  Boolean $allowAdd      Whether values might be added to the
     *                                collection.
     * @param  Boolean $allowDelete   Whether values might be removed from the
     *                                collection.
     * @param  integer $mergeStrategy Which strategy to use for merging the
     *                                bound collection with the original
     *                                collection. Might be any combination of
     *                                MERGE_NORMAL and MERGE_INTO_PARENT.
     *                                MERGE_INTO_PARENT has precedence over
     *                                MERGE_NORMAL if an adder/remover method
     *                                is found. The default strategy is to use
     *                                both strategies.
     * @param  string $addMethod      The name of the adder method to use. If
     *                                not given, the listener tries to discover
     *                                the method automatically.
     * @param  string $removeMethod   The name of the remover method to use. If
     *                                not given, the listener tries to discover
     *                                the method automatically.
     *
     * @throws FormException          If the given strategy is invalid.
     */
    public function __construct($allowAdd = false, $allowDelete = false, $mergeStrategy = null, $addMethod = null, $removeMethod = null)
    {
        if ($mergeStrategy && !($mergeStrategy & (self::MERGE_NORMAL | self::MERGE_INTO_PARENT))) {
            throw new FormException('The merge strategy needs to be at least MERGE_NORMAL or MERGE_INTO_PARENT');
        }

        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->mergeStrategy = $mergeStrategy ?: self::MERGE_NORMAL | self::MERGE_INTO_PARENT;
        $this->addMethod = $addMethod;
        $this->removeMethod = $removeMethod;
    }

    static public function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_BIND => 'preBind',
            FormEvents::BIND_NORM_DATA => 'onBindNormData',
        );
    }

    public function preBind(DataEvent $event)
    {
        // Get a snapshot of the current state of the normalized data
        // to compare against later
        $this->dataSnapshot = $event->getForm()->getNormData();

        if (is_object($this->dataSnapshot)) {
            // Make sure the snapshot remains stable and doesn't change
            $this->dataSnapshot = clone $this->dataSnapshot;
        }

        if (null !== $this->dataSnapshot && !is_array($this->dataSnapshot) && !($this->dataSnapshot instanceof \Traversable && $this->dataSnapshot instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($this->dataSnapshot, 'array or (\Traversable and \ArrayAccess)');
        }
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $originalData = $event->getForm()->getNormData();

        // If we are not allowed to change anything, return immediately
        if (!$this->allowAdd && !$this->allowDelete) {
            // Don't set to the snapshot as then we are switching from the
            // original object to its copy, which might break things
            $event->setData($originalData);
            return;
        }

        $form = $event->getForm();
        $data = $event->getData();
        $parentData = $form->hasParent() ? $form->getParent()->getClientData() : null;
        $addMethod = null;
        $removeMethod = null;
        $getMethod = null;

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
        if (($this->mergeStrategy & self::MERGE_INTO_PARENT) && is_object($parentData)) {
            $plural = ucfirst($form->getName());
            $reflClass = new \ReflectionClass($parentData);
            $addMethodNeeded = $this->allowAdd && !$this->addMethod;
            $removeMethodNeeded = $this->allowDelete && !$this->removeMethod;

            // Any of the two methods is required, but not yet known
            if ($addMethodNeeded || $removeMethodNeeded) {
                $singulars = (array) FormUtil::singularify($plural);

                foreach ($singulars as $singular) {
                    // Try to find adder, but don't override preconfigured one
                    if ($addMethodNeeded) {
                        $addMethod = 'add' . $singular;

                        // False alert
                        if (!$this->isAccessible($reflClass, $addMethod, 1)) {
                            $addMethod = null;
                        }
                    }

                    // Try to find remover, but don't override preconfigured one
                    if ($removeMethodNeeded) {
                        $removeMethod = 'remove' . $singular;

                        // False alert
                        if (!$this->isAccessible($reflClass, $removeMethod, 1)) {
                            $removeMethod = null;
                        }
                    }

                    // Found all that we need. Abort search.
                    if ((!$addMethodNeeded || $addMethod) && (!$removeMethodNeeded || $removeMethod)) {
                        break;
                    }

                    // False alert
                    $addMethod = null;
                    $removeMethod = null;
                }
            }

            // Set preconfigured adder
            if ($this->allowAdd && $this->addMethod) {
                $addMethod = $this->addMethod;

                if (!$this->isAccessible($reflClass, $addMethod, 1)) {
                    throw new FormException(sprintf(
                        'The public method "%s" could not be found on class %s',
                        $addMethod,
                        $reflClass->getName()
                    ));
                }
            }

            // Set preconfigured remover
            if ($this->allowDelete && $this->removeMethod) {
                $removeMethod = $this->removeMethod;

                if (!$this->isAccessible($reflClass, $removeMethod, 1)) {
                    throw new FormException(sprintf(
                        'The public method "%s" could not be found on class %s',
                        $removeMethod,
                        $reflClass->getName()
                    ));
                }
            }

            if ($addMethod || $removeMethod) {
                $getMethod = 'get' . $plural;

                if (!$this->isAccessible($reflClass, $getMethod, 0)) {
                    throw new FormException(sprintf(
                        'The public method "%s" could not be found on class %s',
                        $getMethod,
                        $reflClass->getName()
                    ));
                }
            }
        }

        // Calculate delta between $data and the snapshot created in PRE_BIND
        $itemsToDelete = array();
        $itemsToAdd = is_object($data) ? clone $data : $data;

        if ($this->dataSnapshot) {
            foreach ($this->dataSnapshot as $originalItem) {
                foreach ($data as $key => $item) {
                    if ($item === $originalItem) {
                        // Item found, next original item
                        unset($itemsToAdd[$key]);
                        continue 2;
                    }
                }

                // Item not found, remember for deletion
                foreach ($originalData as $key => $item) {
                    if ($item === $originalItem) {
                        $itemsToDelete[$key] = $item;
                        continue 2;
                    }
                }
            }
        }

        if ($addMethod || $removeMethod) {
            // If methods to add and to remove exist, call them now, if allowed
            if ($removeMethod) {
                foreach ($itemsToDelete as $item) {
                    $parentData->$removeMethod($item);
                }
            }

            if ($addMethod) {
                foreach ($itemsToAdd as $item) {
                    $parentData->$addMethod($item);
                }
            }

            $event->setData($parentData->$getMethod());
        } elseif ($this->mergeStrategy & self::MERGE_NORMAL) {
            if (!$originalData) {
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

    private function isAccessible(\ReflectionClass $reflClass, $methodName, $numberOfRequiredParameters) {
        if ($reflClass->hasMethod($methodName)) {
            $method = $reflClass->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === $numberOfRequiredParameters) {
                return true;
            }
        }

        return false;
    }
}
