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
use Symfony\Component\Form\VirtualFormIterator;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Validator\ValidatorInterface;

class DelegatingValidator implements FormValidatorInterface
{
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
            $mapping = array();
            $forms = array();

            $this->buildMapping($form, $mapping, $forms);
            $this->resolveMappingPlaceholders($mapping, $forms);

            // Validate the form in group "Default"
            // Validation of the data in the custom group is done by validateData(),
            // which is constrained by the Execute constraint
            if ($violations = $this->validator->validate($form)) {
                foreach ($violations as $violation) {
                    $propertyPath = $violation->getPropertyPath();
                    $template = $violation->getMessageTemplate();
                    $parameters = $violation->getMessageParameters();
                    $error = new FormError($template, $parameters);

                    foreach ($mapping as $mappedPath => $child) {
                        if (preg_match($mappedPath, $propertyPath)) {
                            $child->addError($error);
                            continue 2;
                        }
                    }

                    $form->addError($error);
                }
            }
        }
    }

    private function buildMapping(FormInterface $form, array &$mapping,
            array &$forms, $namePath = '', $formPath = '', $dataPath = 'data')
    {
        if ($namePath) {
            $namePath .= '.';
        }

        if ($formPath) {
            $formPath .= '.';
        }

        $iterator = new VirtualFormIterator($form->getChildren());
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $child) {
            $path = (string)$child->getAttribute('property_path');
            $parts = explode('.', $path, 2);

            $nestedNamePath = $namePath . $child->getName();
            $nestedFormPath = $formPath . 'children[' . $parts[0] . ']';

            if (isset($parts[1])) {
                $nestedFormPath .= '.data.' . $parts[1];
            }

            $nestedDataPath = $dataPath . '.' . $path;

            if ($child->hasChildren()) {
                $this->buildMapping($child, $mapping, $forms, $nestedNamePath, $nestedFormPath, $nestedDataPath);
            } else {
                $mapping['/^'.preg_quote($nestedFormPath, '/').'(?!\w)/'] = $child;
                $mapping['/^'.preg_quote($nestedDataPath, '/').'(?!\w)/'] = $child;
            }

            $forms[$nestedNamePath] = $child;
        }

        foreach ($form->getAttribute('error_mapping') as $nestedDataPath => $nestedNamePath)
        {
            $mapping['/^'.preg_quote($formPath . 'data.' . $nestedDataPath).'(?!\w)/'] = $namePath . $nestedNamePath;
            $mapping['/^'.preg_quote($dataPath . '.' . $nestedDataPath).'(?!\w)/'] = $namePath . $nestedNamePath;
        }
    }

    private function resolveMappingPlaceholders(array &$mapping, array $forms)
    {
        foreach ($mapping as $pattern => $form) {
            if (is_string($form)) {
                if (!isset($forms[$form])) {
                    throw new FormException(sprintf('The child form with path "%s" does not exist', $form));
                }

                $mapping[$pattern] = $forms[$form];
            }
        }
    }
}