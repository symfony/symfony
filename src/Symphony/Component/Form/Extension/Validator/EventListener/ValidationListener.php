<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\EventListener;

use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symphony\Component\Validator\Validator\ValidatorInterface;
use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\Extension\Validator\Constraints\Form;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidationListener implements EventSubscriberInterface
{
    private $validator;

    private $violationMapper;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(FormEvents::POST_SUBMIT => 'validateForm');
    }

    public function __construct(ValidatorInterface $validator, ViolationMapperInterface $violationMapper)
    {
        $this->validator = $validator;
        $this->violationMapper = $violationMapper;
    }

    /**
     * Validates the form and its domain object.
     *
     * @param FormEvent $event The event object
     */
    public function validateForm(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot()) {
            // Validate the form in group "Default"
            foreach ($this->validator->validate($form) as $violation) {
                // Allow the "invalid" constraint to be put onto
                // non-synchronized forms
                // ConstraintViolation::getConstraint() must not expect to provide a constraint as long as Symphony\Component\Validator\ExecutionContext exists (before 3.0)
                $allowNonSynchronized = (null === $violation->getConstraint() || $violation->getConstraint() instanceof Form) && Form::NOT_SYNCHRONIZED_ERROR === $violation->getCode();

                $this->violationMapper->mapViolation($violation, $form, $allowNonSynchronized);
            }
        }
    }
}
