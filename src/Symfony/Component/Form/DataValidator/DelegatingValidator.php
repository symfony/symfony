<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataValidator;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\DataError;
use Symfony\Component\Form\FieldError;
use Symfony\Component\Form\PropertyPathIterator;
use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ValidatorInterface;

class DelegatingValidator implements DataValidatorInterface
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validates the form and its domain object
     */
    public function validate(FieldInterface $field)
    {
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

                $this->mapError($error, $field, $iterator);
            }
        }
    }

    private function mapError(Error $error, FieldInterface $field,
            PropertyPathIterator $pathIterator = null)
    {
        if (null !== $pathIterator && $field instanceof FormInterface) {
            if ($error instanceof FieldError && $pathIterator->hasNext()) {
                $pathIterator->next();

                if ($pathIterator->isProperty() && $pathIterator->current() === 'fields') {
                    $pathIterator->next();
                }

                if ($field->has($pathIterator->current())) {
                    $child = $field->get($pathIterator->current());

                    $this->mapError($error, $child, $pathIterator);

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

                            $this->mapError($error, $child, $pathIterator);

                            return;
                        }
                    }
                }
            }
        }

        $field->addError($error);
    }
}