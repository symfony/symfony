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

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
     * @var bool
     */
    private $resizeOnBind;

    public function __construct(FormFactoryInterface $factory, $type, $resizeOnBind = false)
    {
        $this->factory = $factory;
        $this->type = $type;
        $this->resizeOnBind = $resizeOnBind;
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::preSetData,
            Events::preBind,
            Events::filterBoundNormData,
        );
    }

    public function preSetData(DataEvent $event)
    {
        $form = $event->getForm();
        $collection = $event->getData();

        if (null === $collection) {
            $collection = array();
        }

        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'array or \Traversable');
        }

        foreach ($form as $name => $child) {
            if (!$this->resizeOnBind || '$$name$$' != $name) {
                $form->remove($name);
            }
        }

        foreach ($collection as $name => $value) {
            $form->add($this->factory->create($this->type, $name, array(
                'property_path' => '['.$name.']',
            )));
        }
    }

    public function preBind(DataEvent $event)
    {
        if (!$this->resizeOnBind) {
            return;
        }

        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data) {
            $data = array();
        }

        foreach ($form as $name => $child) {
            if (!isset($data[$name]) && '$$name$$' != $name) {
                $form->remove($name);
            }
        }

        foreach ($data as $name => $value) {
            if (!$form->has($name)) {
                $form->add($this->factory->create($this->type, $name, array(
                    'property_path' => '['.$name.']',
                )));
            }
        }
    }

    public function filterBoundNormData(FilterDataEvent $event)
    {
        if (!$this->resizeOnBind) {
            return;
        }

        $form = $event->getForm();
        $collection = $event->getData();

        foreach ($collection as $name => $child) {
            if (!$form->has($name)) {
                unset($collection[$name]);
            }
        }

        $event->setData($collection);
    }
}