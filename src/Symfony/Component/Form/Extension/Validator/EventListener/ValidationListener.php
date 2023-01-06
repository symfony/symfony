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
use Symfony\Component\Form\Extension\Validator\Constraints\Form;
use Symfony\Component\Form\Extension\Validator\ViolationMapper\ViolationMapperInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidationListener implements EventSubscriberInterface
{
    private ValidatorInterface $validator;
    private ViolationMapperInterface $violationMapper;

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::POST_SUBMIT => 'validateForm'];
    }

    public function __construct(ValidatorInterface $validator, ViolationMapperInterface $violationMapper)
    {
        $this->validator = $validator;
        $this->violationMapper = $violationMapper;
    }

    public function validateForm(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->isRoot()) {
            // Form groups are validated internally (FormValidator). Here we don't set groups as they are retrieved into the validator.
            foreach ($this->validator->validate($form) as $violation) {
                // Allow the "invalid" constraint to be put onto
                // non-synchronized forms
                $allowNonSynchronized = $violation->getConstraint() instanceof Form && Form::NOT_SYNCHRONIZED_ERROR === $violation->getCode();

                $this->violationMapper->mapViolation($violation, $form, $allowNonSynchronized);
            }
        }
    }
}
