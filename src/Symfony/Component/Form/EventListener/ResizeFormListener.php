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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResizeFormListener implements EventSubscriberInterface
{
    private $factory;

    private $type;

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
        );
    }

    public function preSetData(DataEvent $event)
    {
        $form = $event->getField();
        $collection = $event->getData();

        if (null === $collection) {
            $collection = array();
        }

        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'array or \Traversable');
        }

        foreach ($form as $name => $field) {
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
        $form = $event->getField();
        $data = $event->getData();

        $this->removedFields = array();

        if (null === $data) {
            $data = array();
        }

        foreach ($form as $name => $field) {
            if (!isset($data[$name]) && $this->resizeOnBind && '$$name$$' != $name) {
                $form->remove($name);
                $this->removedFields[] = $name;
            }
        }

        foreach ($data as $name => $value) {
            if (!$form->has($name) && $this->resizeOnBind) {
                $form->add($this->factory->create($this->type, $name, array(
                    'property_path' => '['.$name.']',
                )));
            }
        }
    }
}