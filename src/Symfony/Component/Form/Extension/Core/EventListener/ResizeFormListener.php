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

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ResizeFormListener implements EventSubscriberInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $options;

    /**
     * Whether children could be added to the group
     * @var Boolean
     */
    private $allowAdd;

    /**
     * Whether children could be removed from the group
     * @var Boolean
     */
    private $allowDelete;

    public function __construct(FormFactoryInterface $factory, $type, array $options = array(), $allowAdd = false, $allowDelete = false)
    {
        $this->factory = $factory;
        $this->type = $type;
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->options = $options;
    }

    static public function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_BIND => 'preBind',
            FormEvents::BIND_NORM_DATA => 'onBindNormData',
        );
    }

    public function preSetData(DataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }

        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            $form->add($this->factory->createNamed($this->type, $name, null, array_replace(array(
                'property_path' => '['.$name.']',
            ), $this->options)));
        }
    }

    public function preBind(DataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data || '' === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // Remove all empty rows
        if ($this->allowDelete) {
            foreach ($form as $name => $child) {
                if (!isset($data[$name])) {
                    $form->remove($name);
                }
            }
        }

        // Add all additional rows
        if ($this->allowAdd) {
            foreach ($data as $name => $value) {
                if (!$form->has($name)) {
                    $form->add($this->factory->createNamed($this->type, $name, null, array_replace(array(
                        'property_path' => '['.$name.']',
                    ), $this->options)));
                }
            }
        }
    }

    public function onBindNormData(FilterDataEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if ($this->allowDelete) {
            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    unset($data[$name]);
                }
            }
        }

        $event->setData($data);
    }
}
