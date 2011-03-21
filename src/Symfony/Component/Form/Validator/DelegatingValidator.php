<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Validator;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\PropertyPath;
use Symfony\Component\Form\PropertyPathIterator;
use Symfony\Component\Validator\ValidatorInterface;

class DelegatingValidator implements FormValidatorInterface
{
    const DATA_ERROR = 0;

    const FORM_ERROR = 1;

    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validates the form and its domain object
     */
    public function validate(FormInterface $form)
    {
        if ($form->isRoot()) {
            // Validate the form in group "Default"
            // Validation of the data in the custom group is done by validateData(),
            // which is constrained by the Execute constraint
            if ($violations = $this->validator->validate($form)) {
                foreach ($violations as $violation) {
                    $propertyPath = new PropertyPath($violation->getPropertyPath());
                    $iterator = $propertyPath->getIterator();
                    $template = $violation->getMessageTemplate();
                    $parameters = $violation->getMessageParameters();
                    $error = new FormError($template, $parameters);

                    if ($iterator->current() == 'data') {
                        $iterator->next(); // point at the first data element
                        $type = self::DATA_ERROR;
                    } else {
                        $type = self::FORM_ERROR;
                    }

                    $this->mapError($error, $form, $iterator, $type);
                }
            }
        }
    }

    private function mapError(FormError $error, FormInterface $form,
            PropertyPathIterator $pathIterator, $type)
    {
        if (null !== $pathIterator && $form instanceof FormInterface) {
            if ($type === self::FORM_ERROR && $pathIterator->hasNext()) {
                $pathIterator->next();

                if ($pathIterator->isProperty() && $pathIterator->current() === 'forms') {
                    $pathIterator->next();
                }

                if ($form->has($pathIterator->current())) {
                    $child = $form->get($pathIterator->current());

                    $this->mapError($error, $child, $pathIterator, $type);

                    return;
                }
            } else if ($type === self::DATA_ERROR) {
                $iterator = new RecursiveFieldIterator($form);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $child) {
                    if (null !== ($childPath = $child->getAttribute('property_path'))) {
                        if ($childPath->getElement(0) === $pathIterator->current()) {
                            if ($pathIterator->hasNext()) {
                                $pathIterator->next();
                            }

                            $this->mapError($error, $child, $pathIterator, $type);

                            return;
                        }
                    }
                }
            }
        }

        $form->addError($error);
    }
}