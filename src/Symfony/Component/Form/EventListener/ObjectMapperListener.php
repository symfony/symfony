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

use Symfony\Component\Form\RecursiveFieldIterator;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ObjectMapperListener implements EventSubscriberInterface
{
    /**
     * Stores the class that the data of this form must be instances of
     * @var string
     */
    private $dataClass;

    /**
     * Stores the constructor closure for creating new domain object instances
     * @var \Closure
     */
    private $dataConstructor;

    public function __construct($dataClass = null, $dataConstructor = null)
    {
        $this->dataClass = $dataClass;
        $this->dataConstructor = $dataConstructor;
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::postSetData,
            Events::filterSetData,
            Events::filterBoundDataFromClient,
        );
    }

    public function postSetData(DataEvent $event)
    {
        // get transformed data and pass its values to child fields
        $form = $event->getField();
        $data = $form->getTransformedData();

        if (!empty($data) && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type object or array, %s given', gettype($data)));
        }

        if (!empty($data)) {
            if ($this->dataClass && !$data instanceof $this->dataClass) {
                throw new FormException(sprintf('Form data should be instance of %s', $this->dataClass));
            }

            $iterator = new RecursiveFieldIterator($form);
            $iterator = new \RecursiveIteratorIterator($iterator);

            foreach ($iterator as $field) {
                if ($field->getPropertyPath() !== null) {
                    $field->setData($field->getPropertyPath()->getValue($data));
                }
            }
        }
    }

    public function filterSetData(FilterDataEvent $event)
    {
        $field = $event->getField();

        if (null === $field->getValueTransformer() && null === $field->getNormalizationTransformer()) {
            $data = $event->getData();

            // Empty values must be converted to objects or arrays so that
            // they can be read by PropertyPath in the child fields
            if (empty($data)) {
                if ($this->dataConstructor) {
                    $constructor = $this->dataConstructor;
                    $event->setData($constructor());
                } else if ($this->dataClass) {
                    $class = $this->dataClass;
                    $event->setData(new $class());
                } else {
                    $event->setData(array());
                }
            }
        }
    }

    public function filterBoundDataFromClient(FilterDataEvent $event)
    {
        $form = $event->getField();
        $data = $form->getTransformedData();

        $iterator = new RecursiveFieldIterator($form);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $isReference = false;

            // If the data is identical to the value in $data, we are
            // dealing with a reference
            if ($field->getPropertyPath() !== null) {
                $isReference = $field->getData() === $field->getPropertyPath()->getValue($data);
            }

            // Don't write into $data if $data is an object,
            // $isReference is true (see above) and the option "by_reference" is
            // true as well
            if (!is_object($data) || !$isReference || !$field->isModifiedByReference()) {
                if ($field->getPropertyPath() !== null) {
                    $field->getPropertyPath()->setValue($data, $field->getData());
                }
            }
        }

        $event->setData($data);
    }
}