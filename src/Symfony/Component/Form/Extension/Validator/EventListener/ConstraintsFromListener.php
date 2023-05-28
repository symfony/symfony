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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ConstraintsFromListener implements EventSubscriberInterface
{
    private ValidatorInterface $validator;

    public static function getSubscribedEvents(): array
    {
        return [FormEvents::POST_SUBMIT => 'validateConstraints'];
    }

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function validateConstraints(FormEvent $event): void
    {
        $form = $event->getForm();

        $entity = $form->getConfig()->getOption('constraints_from_entity');

        if (null !== $entity) {
            $property = $form->getConfig()->getOption('constraints_from_property') ?? $form->getName();

            $violations = $this->validator->validatePropertyValue($entity, $property, $event->getData());
            /** @var ConstraintViolationInterface $violation */
            foreach ($violations as $violation) {
                $form->addError(
                    new FormError(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getParameters(),
                        $violation->getPlural()
                    )
                );
            }
        }
    }
}
