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
     * The name of the adder method to look for
     * @var string
     */
    private $addMethod;

    /**
     * The name of the remover method to look for
     * @var string
     */
    private $removeMethod;

    public function __construct($allowAdd = false, $allowDelete = false, $useAccessors = true, $addMethod = null, $removeMethod = null)
    {
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->useAccessors = $useAccessors;
        $this->addMethod = $addMethod;
        $this->removeMethod = $removeMethod;
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
        $addMethod = null;
        $removeMethod = null;

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
            $reflClass = new \ReflectionClass($parentData);
            $addMethodNeeded = $this->allowAdd && !$this->addMethod;
            $removeMethodNeeded = $this->allowDelete && !$this->removeMethod;

            // Any of the two methods is required, but not yet known
            if ($addMethodNeeded || $removeMethodNeeded) {
                $singulars = (array) FormUtil::singularify(ucfirst($form->getName()));

                foreach ($singulars as $singular) {
                    // Try to find adder, but don't override preconfigured one
                    if ($addMethodNeeded) {
                        $addMethod = $this->checkMethod($reflClass, 'add' . $singular);
                    }

                    // Try to find remover, but don't override preconfigured one
                    if ($removeMethodNeeded) {
                        $removeMethod = $this->checkMethod($reflClass, 'remove' . $singular);
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
                $addMethod = $this->checkMethod($reflClass, $this->addMethod);

                if (!$addMethod) {
                    throw new FormException(sprintf(
                        'The method "%s" could not be found on class %s',
                        $this->addMethod,
                        $reflClass->getName()
                    ));
                }
            }

            // Set preconfigured remover
            if ($this->allowDelete && $this->removeMethod) {
                $removeMethod = $this->checkMethod($reflClass, $this->removeMethod);

                if (!$removeMethod) {
                    throw new FormException(sprintf(
                        'The method "%s" could not be found on class %s',
                        $this->removeMethod,
                        $reflClass->getName()
                    ));
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

    private function checkMethod(\ReflectionClass $reflClass, $methodName) {
        if ($reflClass->hasMethod($methodName)) {
            $method = $reflClass->getMethod($methodName);

            if ($method->isPublic() && $method->getNumberOfRequiredParameters() === 1) {
                return $methodName;
            }
        }

        return null;
    }
}
