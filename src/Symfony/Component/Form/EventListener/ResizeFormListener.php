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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ResizeFormListener implements EventListenerInterface
{
    private $form;

    private $prototype;

    private $resizeOnBind;

    public function __construct(FormInterface $form, FieldInterface $prototype, $resizeOnBind = false)
    {
        $this->form = $form;
        $this->prototype = $prototype;
        $this->resizeOnBind = $resizeOnBind;
    }

    public function getSupportedEvents()
    {
        return array(
            Events::preSetData,
            Events::preBind,
        );
    }

    public function preSetData($collection)
    {
        if (null === $collection) {
            $collection = array();
        }

        if (!is_array($collection) && !$collection instanceof \Traversable) {
            throw new UnexpectedTypeException($collection, 'array or \Traversable');
        }

        foreach ($this->form as $name => $field) {
            if (!$this->resizeOnBind || '$$key$$' != $name) {
                $this->form->remove($name);
            }
        }

        foreach ($collection as $name => $value) {
            $this->form->add($this->newField($name));
        }
    }

    public function preBind($data)
    {
        $this->removedFields = array();

        if (null === $data) {
            $data = array();
        }

        foreach ($this->form as $name => $field) {
            if (!isset($data[$name]) && $this->resizeOnBind && '$$key$$' != $name) {
                $this->form->remove($name);
                $this->removedFields[] = $name;
            }
        }

        foreach ($data as $name => $value) {
            if (!$this->form->has($name) && $this->resizeOnBind) {
                $this->form->add($this->newField($name));
            }
        }
    }

    protected function newField($key)
    {
        $field = clone $this->prototype;
        $field->setKey($key);
        $field->setPropertyPath('['.$key.']');

        return $field;
    }
}