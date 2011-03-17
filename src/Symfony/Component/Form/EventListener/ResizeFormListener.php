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
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ResizeFormListener implements EventSubscriberInterface
{
    private $form;

    private $prototype;

    private $resizeOnBind;

    public function __construct(FormInterface $form, $prototype, $resizeOnBind = false)
    {
        $this->form = $form;
        $this->prototype = $prototype;
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
        $collection = $event->getData();

        if (null === $collection) {
            $collection = array();
        }

        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'array or \Traversable');
        }

        foreach ($this->form as $name => $field) {
            if (!$this->resizeOnBind || '$$name$$' != $name) {
                $this->form->remove($name);
            }
        }

        foreach ($collection as $name => $value) {
            $this->form->add($this->prototype, $name, array(
                'property_path' => '['.$name.']',
            ));
        }
    }

    public function preBind(DataEvent $event)
    {
        $data = $event->getData();

        $this->removedFields = array();

        if (null === $data) {
            $data = array();
        }

        foreach ($this->form as $name => $field) {
            if (!isset($data[$name]) && $this->resizeOnBind && '$$name$$' != $name) {
                $this->form->remove($name);
                $this->removedFields[] = $name;
            }
        }

        foreach ($data as $name => $value) {
            if (!$this->form->has($name) && $this->resizeOnBind) {
                $this->form->add($this->prototype, $name, array(
                    'property_path' => '['.$name.']',
                ));
            }
        }
    }
}