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
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResizeFormListener implements EventSubscriberInterface
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $options;

    /**
     * Whether children could be added to the group.
     *
     * @var bool
     */
    protected $allowAdd;

    /**
     * Whether children could be removed from the group.
     *
     * @var bool
     */
    protected $allowDelete;

    /**
     * @var bool
     */
    private $deleteEmpty;

    public function __construct($entryType, array $entryOptions = array(), $allowAdd = false, $allowDelete = false, $deleteEmpty = false)
    {
        $this->type = $entryType;
        $this->allowAdd = $allowAdd;
        $this->allowDelete = $allowDelete;
        $this->options = $entryOptions;
        $this->deleteEmpty = $deleteEmpty;
    }

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'preSetData',
            FormEvents::PRE_SUBMIT => 'preSubmit',
            // (MergeCollectionListener, MergeDoctrineCollectionListener)
            FormEvents::SUBMIT => array('onSubmit', 50),
        );
    }

    public function preSetData(FormEvent $event)
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
            $form->add($name, $this->type, array_replace(array(
                'property_path' => '['.$name.']',
            ), $this->options));
        }
    }

    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (null === $data || '' === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            $data = array();
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
                    $form->add($name, $this->type, array_replace(array(
                        'property_path' => '['.$name.']',
                    ), $this->options));
                }
            }
        }
    }

    public function onSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        // At this point, $data is an array or an array-like object that already contains the
        // new entries, which were added by the data mapper. The data mapper ignores existing
        // entries, so we need to manually unset removed entries in the collection.

        if (null === $data) {
            $data = array();
        }

        if (!is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if ($this->deleteEmpty) {
            $previousData = $event->getForm()->getData();
            foreach ($form as $name => $child) {
                $isNew = !isset($previousData[$name]);

                // $isNew can only be true if allowAdd is true, so we don't
                // need to check allowAdd again
                if ($child->isEmpty() && ($isNew || $this->allowDelete)) {
                    unset($data[$name]);
                    $form->remove($name);
                }
            }
        }

        // The data mapper only adds, but does not remove items, so do this
        // here
        if ($this->allowDelete) {
            $toDelete = array();

            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    $toDelete[] = $name;
                }
            }

            foreach ($toDelete as $name) {
                unset($data[$name]);
            }
        }

        $event->setData($data);
    }

    /**
     * Alias of {@link preSubmit()}.
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     *             Use {@link preSubmit()} instead.
     */
    public function preBind(FormEvent $event)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the preSubmit() method instead.', E_USER_DEPRECATED);

        $this->preSubmit($event);
    }

    /**
     * Alias of {@link onSubmit()}.
     *
     * @deprecated since version 2.3, to be removed in 3.0.
     *             Use {@link onSubmit()} instead.
     */
    public function onBind(FormEvent $event)
    {
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the onSubmit() method instead.', E_USER_DEPRECATED);

        $this->onSubmit($event);
    }
}
