<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Dynamic;

use Symfony\Component\Form\ClearableErrorsInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

/**
 * Adds and removes fields depending on the data of other fields.
 *
 * The callbacks run when the form is first created (with the initial data) and
 * again when it is submitted. A handler follows a single form lifecycle: the
 * executed callbacks are not reset between two uses of the same builder.
 *
 * @author Ryan Weaver
 * @author Benjamin Georgeault <git@wedgesama.fr>
 *
 * @internal
 */
final class DynamicHandler
{
    /**
     * @var DynamicFieldConfig[]
     */
    private array $dynamicFieldConfigs = [];

    /**
     * The actual form that the builder is turned into.
     */
    private FormInterface $form;

    private array $preSetDataDependencyData = [];
    private array $postSubmitDependencyData = [];

    /**
     * The names of the data submitted to the form.
     *
     * @var array<string, true>
     */
    private array $submittedNames = [];

    public function __construct(private FormBuilderInterface $builder)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $this->form = $event->getForm();
            $this->preSetDataDependencyData = [];

            $this->initializeListeners();

            // Holds the error of a dynamic field whose submitted data is no longer valid.
            // A hidden field is used so that no error is rendered next to a field.
            if (!$this->form->has('_dynamic_error')) {
                $this->form->add('_dynamic_error', HiddenType::class, [
                    'mapped' => false,
                    'error_bubbling' => false,
                ]);
            }
        }, 100);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();

            $this->submittedNames = \is_array($data) ? array_fill_keys(array_keys($data), true) : [];
        }, -100);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function () {
            $this->postSubmitDependencyData = [];
        });

        // must run after the ValidationListener
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->clearDataOnTransformationError(...), -1);
    }

    public function add(string $name, string|array $dependencies, callable $callback): void
    {
        $this->dynamicFieldConfigs[] = new DynamicFieldConfig($name, (array) $dependencies, $callback(...));
    }

    private function storePreSetDataDependencyData(FormEvent $event): void
    {
        $this->preSetDataDependencyData[$event->getForm()->getName()] = $event->getData();

        $this->executeReadyCallbacks($this->preSetDataDependencyData, FormEvents::PRE_SET_DATA);
    }

    private function storePostSubmitDependencyData(FormEvent $event): void
    {
        $this->postSubmitDependencyData[$event->getForm()->getName()] = $event->getForm()->getData();

        $this->executeReadyCallbacks($this->postSubmitDependencyData, FormEvents::POST_SUBMIT);
    }

    private function clearDataOnTransformationError(FormEvent $event): void
    {
        $form = $event->getForm();
        $transformationErrorsCleared = false;

        foreach ($this->dynamicFieldConfigs as $dynamicFieldConfig) {
            if (!$form->has($dynamicFieldConfig->name)) {
                continue;
            }

            $subForm = $form->get($dynamicFieldConfig->name);
            if ($subForm->getTransformationFailure() && $subForm instanceof ClearableErrorsInterface) {
                $subForm->clearErrors();
                $transformationErrorsCleared = true;
            }
        }

        if ($transformationErrorsCleared) {
            // The error is cleared, but the bad data remains on the field. The form must
            // not be valid, and no validation error should be rendered on any field.
            if ($form->get('_dynamic_error')->isValid()) {
                $form->get('_dynamic_error')->addError(new FormError('Some dynamic fields have errors.'));
            }
        }
    }

    private function executeReadyCallbacks(array $availableDependencyData, string $eventName): void
    {
        foreach ($this->dynamicFieldConfigs as $dynamicFieldConfig) {
            if (!$dynamicFieldConfig->isReady($availableDependencyData, $eventName)) {
                continue;
            }

            $dynamicField = $dynamicFieldConfig->execute($availableDependencyData, $eventName);
            $name = $dynamicFieldConfig->name;

            if (!$dynamicField->shouldBeAdded()) {
                $this->removeField($name, $eventName);

                continue;
            }

            $this->builder->add($name, $dynamicField->getType(), $dynamicField->getOptions());

            $this->initializeListeners([$name]);

            // auto initialize mimics FormBuilder::getForm() behavior
            $this->form->add($this->builder->get($name)->setAutoInitialize(false)->getForm());
        }
    }

    private function removeField(string $name, string $eventName): void
    {
        $isSubmitted = $this->form->has($name) && $this->form->get($name)->isSubmitted();

        if (FormEvents::POST_SUBMIT !== $eventName || $isSubmitted || !isset($this->submittedNames[$name])) {
            $this->form->remove($name);

            return;
        }

        // The field is removed while the children are submitted, before its turn, and some data was
        // submitted for it. The form would report it as extra data, because a child removed before being
        // submitted never consumes its data. A placeholder takes its place: it consumes the data, then removes itself.
        $placeholder = $this->builder->getFormFactory()->createNamedBuilder($name, HiddenType::class, null, [
            'mapped' => false,
            'auto_initialize' => false,
        ]);
        $placeholder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) {
            $event->getForm()->getParent()?->remove($event->getForm()->getName());
        });

        $this->form->add($placeholder->getForm());
    }

    private function initializeListeners(?array $fieldsToConsider = null): void
    {
        $registeredFields = [];

        foreach ($this->dynamicFieldConfigs as $dynamicFieldConfig) {
            foreach ($dynamicFieldConfig->dependencies as $dependency) {
                if ($fieldsToConsider && !\in_array($dependency, $fieldsToConsider, true)) {
                    continue;
                }

                // skip dependencies that are possibly not *yet* part of the form
                if (!$this->builder->has($dependency)) {
                    continue;
                }

                if (\in_array($dependency, $registeredFields, true)) {
                    continue;
                }

                $registeredFields[] = $dependency;

                $this->builder->get($dependency)->addEventListener(FormEvents::PRE_SET_DATA, $this->storePreSetDataDependencyData(...));
                $this->builder->get($dependency)->addEventListener(FormEvents::POST_SUBMIT, $this->storePostSubmitDependencyData(...));
            }
        }
    }
}
