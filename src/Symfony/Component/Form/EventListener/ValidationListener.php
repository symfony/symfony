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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\DataError;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\PropertyPathIterator;
use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ValidatorInterface;

class ValidationListener implements EventSubscriberInterface
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::postBind,
        );
    }

    /**
     * Validates the form and its domain object
     */
    public function postBind(DataEvent $event)
    {
        $field = $event->getField();

        if ($field->isRoot()) {
            // Validate the field in group "Default"
            // Validation of the data in the custom group is done by validateData(),
            // which is constrained by the Execute constraint
            if ($violations = $this->validator->validate($field)) {
                foreach ($violations as $violation) {
                    $propertyPath = new PropertyPath($violation->getPropertyPath());
                    $iterator = $propertyPath->getIterator();
                    $template = $violation->getMessageTemplate();
                    $parameters = $violation->getMessageParameters();

                    if ($iterator->current() == 'data') {
                        $iterator->next(); // point at the first data element
                        $error = new DataError($template, $parameters);
                    } else {
                        $error = new FieldError($template, $parameters);
                    }

                    $this->mapError($field, $error, $iterator);
                }
            }
        }
    }

    private function mapError(FieldInterface $form, Error $error,
            PropertyPathIterator $pathIterator = null)
    {
        if (null !== $pathIterator && $field instanceof FieldInterface) {
            if ($error instanceof FieldError && $pathIterator->hasNext()) {
                $pathIterator->next();

                if ($pathIterator->isProperty() && $pathIterator->current() === 'fields') {
                    $pathIterator->next();
                }

                if ($field->has($pathIterator->current())) {
                    $child = $field->get($pathIterator->current());

                    $this->mapError($child, $error, $pathIterator);

                    return;
                }
            } else if ($error instanceof DataError) {
                $iterator = new RecursiveFieldIterator($field);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $child) {
                    if (null !== ($childPath = $child->getAttribute('property_path'))) {
                        if ($childPath->getElement(0) === $pathIterator->current()) {
                            if ($pathIterator->hasNext()) {
                                $pathIterator->next();
                            }

                            $this->mapError($child, $error, $pathIterator);

                            return;
                        }
                    }
                }
            }
        }

        $field->addError($error);
    }
}