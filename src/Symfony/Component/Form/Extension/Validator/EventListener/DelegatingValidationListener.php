<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Validator\ExecutionContext;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DelegatingValidationListener implements EventSubscriberInterface
{
    private $validator;

    /**
     * {@inheritdoc}
     */
    static public function getSubscribedEvents()
    {
        return array(FormEvents::POST_BIND => 'validateForm');
    }

    /**
     * Validates the data of a form
     *
     * This method is called automatically during the validation process.
     *
     * @param FormInterface    $form    The validated form
     * @param ExecutionContext $context The current validation context
     */
    static public function validateFormData(FormInterface $form, ExecutionContext $context)
    {
        if (is_object($form->getData()) || is_array($form->getData())) {
            $propertyPath = $context->getPropertyPath();
            $graphWalker = $context->getGraphWalker();

            // Adjust the property path accordingly
            if (!empty($propertyPath)) {
                $propertyPath .= '.';
            }

            $propertyPath .= 'data';

            foreach (self::getFormValidationGroups($form) as $group) {
                $graphWalker->walkReference($form->getData(), $group, $propertyPath, true);
            }
        }
    }

    static public function validateFormChildren(FormInterface $form, ExecutionContext $context)
    {
        if ($form->getAttribute('cascade_validation')) {
            $propertyPath = $context->getPropertyPath();
            $graphWalker = $context->getGraphWalker();

            // Adjust the property path accordingly
            if (!empty($propertyPath)) {
                $propertyPath .= '.';
            }

            $propertyPath .= 'children';

            $graphWalker->walkReference($form->getChildren(), Constraint::DEFAULT_GROUP, $propertyPath, true);
        }
    }

    static protected function getFormValidationGroups(FormInterface $form)
    {
        $groups = null;

        if ($form->hasAttribute('validation_groups')) {
            $groups = $form->getAttribute('validation_groups');

            if (is_callable($groups)) {
                $groups = (array) call_user_func($groups, $form);
            }
        }

        $currentForm = $form;
        while (!$groups && $currentForm->hasParent()) {
            $currentForm = $currentForm->getParent();

            if ($currentForm->hasAttribute('validation_groups')) {
                $groups = $currentForm->getAttribute('validation_groups');

                if (is_callable($groups)) {
                    $groups = (array) call_user_func($groups, $currentForm);
                }
            }
        }

        if (null === $groups) {
            $groups = array('Default');
        }

        return (array) $groups;
    }

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validates the form and its domain object.
     *
     * @param DataEvent $event The event object
     */
    public function validateForm(DataEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot()) {
            // Validate the form in group "Default"
            // Validation of the data in the custom group is done by validateData(),
            // which is constrained by the Execute constraint
            if ($form->hasAttribute('validation_constraint')) {
                $violations = $this->validator->validateValue(
                    $form->getData(),
                    $form->getAttribute('validation_constraint'),
                    self::getFormValidationGroups($form)
                );
            } else {
                $violations = $this->validator->validate($form);
            }

            if (count($violations) > 0) {
                $mapper = new ViolationMapper();

                foreach ($violations as $violation) {
                    $mapper->mapViolation($violation, $form);
                }
            }
        }
    }
}
