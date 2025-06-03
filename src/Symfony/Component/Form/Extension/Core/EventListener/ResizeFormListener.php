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
use Symfony\Component\Form\Event\PostSetDataEvent;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

/**
 * Resize a collection form element based on the data sent from the client.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ResizeFormListener implements EventSubscriberInterface
{
    protected array $prototypeOptions;

    private \Closure|bool $deleteEmpty;
    // BC, to be removed in 8.0
    private bool $overridden = true;
    private bool $usePreSetData = false;

    public function __construct(
        private string $type,
        private array $options = [],
        private bool $allowAdd = false,
        private bool $allowDelete = false,
        bool|callable $deleteEmpty = false,
        ?array $prototypeOptions = null,
        private bool $keepAsList = false,
    ) {
        $this->deleteEmpty = \is_bool($deleteEmpty) ? $deleteEmpty : $deleteEmpty(...);
        $this->prototypeOptions = $prototypeOptions ?? $options;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'preSetData', // deprecated
            FormEvents::POST_SET_DATA => ['postSetData', 255], // as early as possible
            FormEvents::PRE_SUBMIT => 'preSubmit',
            // (MergeCollectionListener, MergeDoctrineCollectionListener)
            FormEvents::SUBMIT => ['onSubmit', 50],
        ];
    }

    /**
     * @deprecated Since Symfony 7.2, use {@see postSetData()} instead.
     */
    public function preSetData(FormEvent $event): void
    {
        if (__CLASS__ === static::class
            || __CLASS__ === (new \ReflectionClass($this))->getMethod('preSetData')->getDeclaringClass()->name
        ) {
            // not a child class, or child class does not overload PRE_SET_DATA
            return;
        }

        trigger_deprecation('symfony/form', '7.2', 'Calling "%s()" is deprecated, use "%s::postSetData()" instead.', __METHOD__, __CLASS__);
        // parent::preSetData() has been called
        $this->overridden = false;
        try {
            $this->postSetData($event);
        } finally {
            $this->usePreSetData = true;
        }
    }

    /**
     * Remove FormEvent type hint in 8.0.
     *
     * @final since Symfony 7.2
     */
    public function postSetData(FormEvent|PostSetDataEvent $event): void
    {
        if (__CLASS__ !== static::class) {
            if ($this->overridden) {
                trigger_deprecation('symfony/form', '7.2', 'Calling "%s::preSetData()" is deprecated, use "%s::postSetData()" instead.', static::class, __CLASS__);
                // parent::preSetData() has not been called, noop

                return;
            }

            if ($this->usePreSetData) {
                // nothing else to do
                return;
            }
        }

        $form = $event->getForm();
        $data = $event->getData() ?? [];

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        // First remove all rows
        foreach ($form as $name => $child) {
            $form->remove($name);
        }

        // Then add all rows again in the correct order
        foreach ($data as $name => $value) {
            $form->add($name, $this->type, array_replace([
                'property_path' => '['.$name.']',
            ], $this->options));
        }
    }

    public function preSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData();

        if (!\is_array($data)) {
            $data = [];
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
                    $form->add($name, $this->type, array_replace([
                        'property_path' => '['.$name.']',
                    ], $this->prototypeOptions));
                }
            }
        }
    }

    public function onSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $data = $event->getData() ?? [];

        // At this point, $data is an array or an array-like object that already contains the
        // new entries, which were added by the data mapper. The data mapper ignores existing
        // entries, so we need to manually unset removed entries in the collection.

        if (!\is_array($data) && !($data instanceof \Traversable && $data instanceof \ArrayAccess)) {
            throw new UnexpectedTypeException($data, 'array or (\Traversable and \ArrayAccess)');
        }

        if ($this->deleteEmpty) {
            $previousData = $form->getData();
            /** @var FormInterface $child */
            foreach ($form as $name => $child) {
                if (!$child->isValid() || !$child->isSynchronized()) {
                    continue;
                }

                $isNew = !isset($previousData[$name]);
                $isEmpty = \is_callable($this->deleteEmpty) ? ($this->deleteEmpty)($child->getData()) : $child->isEmpty();

                // $isNew can only be true if allowAdd is true, so we don't
                // need to check allowAdd again
                if ($isEmpty && ($isNew || $this->allowDelete)) {
                    unset($data[$name]);
                    $form->remove($name);
                }
            }
        }

        // The data mapper only adds, but does not remove items, so do this
        // here
        if ($this->allowDelete) {
            $toDelete = [];

            foreach ($data as $name => $child) {
                if (!$form->has($name)) {
                    $toDelete[] = $name;
                }
            }

            foreach ($toDelete as $name) {
                unset($data[$name]);
            }
        }

        if ($this->keepAsList) {
            $formReindex = [];
            foreach ($form as $name => $child) {
                $formReindex[] = $child;
                $form->remove($name);
            }
            foreach ($formReindex as $index => $child) {
                $form->add($index, $this->type, array_replace([
                    'property_path' => '['.$index.']',
                ], $this->options));
            }
            $data = array_values($data);
        }

        $event->setData($data);
    }
}
