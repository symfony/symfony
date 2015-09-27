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
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Validator\Constraints\Form;

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

    /**
     * @param ValidatorInterface|LegacyValidatorInterface $validator
     * @param ViolationMapperInterface                    $violationMapper
     */
    public function __construct($validator, ViolationMapperInterface $violationMapper)
    {
        if (!$validator instanceof ValidatorInterface && !$validator instanceof LegacyValidatorInterface) {
            throw new \InvalidArgumentException('Validator must be instance of Symfony\Component\Validator\Validator\ValidatorInterface or Symfony\Component\Validator\ValidatorInterface');
        }

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
            $violations = $this->validator->validate($form);

            foreach ($violations as $violation) {
                // Allow the "invalid" constraint to be put onto
                // non-synchronized forms
                // ConstraintViolation::getConstraint() must not expect to provide a constraint as long as Symfony\Component\Validator\ExecutionContext exists (before 3.0)
                $allowNonSynchronized = (null === $violation->getConstraint() || $violation->getConstraint() instanceof Form) && Form::NOT_SYNCHRONIZED_ERROR === $violation->getCode();

                $this->violationMapper->mapViolation($violation, $form, $allowNonSynchronized);
            }
        }
    }
}
