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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Util\VirtualFormAwareIterator;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\ConstraintViolationList;

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
            $mapping = array();
            $forms = array();

            $this->buildFormPathMapping($form, $mapping);
            $this->buildDataPathMapping($form, $mapping);
            $this->buildNamePathMapping($form, $forms);
            $this->resolveMappingPlaceholders($mapping, $forms);

            // Validate the form in group "Default"
            // Validation of the data in the custom group is done by validateData(),
            // which is constrained by the Execute constraint
            if ($form->hasAttribute('validation_constraint')) {
                if (is_array($form->getAttribute('validation_constraint'))) {
                    $violations = new ConstraintViolationList();
                    foreach ($form->getAttribute('validation_constraint') as $constraint) {
                        $newViolations = $this->validator->validateValue(
                            $form->getData(),
                            $constraint,
                            self::getFormValidationGroups($form)
                        );
                        if (null !== $newViolations) {
                            $violations->addAll($newViolations);
                        }
                    }
                } else {
                    $violations = $this->validator->validateValue(
                        $form->getData(),
                        $form->getAttribute('validation_constraint'),
                        self::getFormValidationGroups($form)
                    );
                }

                if ($violations) {
                    foreach ($violations as $violation) {
                        $propertyPath = new PropertyPath($violation->getPropertyPath());
                        $template = $violation->getMessageTemplate();
                        $parameters = $violation->getMessageParameters();
                        $pluralization = $violation->getMessagePluralization();
                        $error = new FormError($template, $parameters, $pluralization);

                        $child = $form;
                        foreach ($propertyPath->getElements() as $element) {
                            $children = $child->getChildren();
                            if (!isset($children[$element])) {
                                $form->addError($error);
                                break;
                            }

                            $child = $children[$element];
                        }

                        $child->addError($error);
                    }
                }
            } elseif (count($violations = $this->validator->validate($form))) {
                foreach ($violations as $violation) {
                    $propertyPath = $violation->getPropertyPath();
                    $template = $violation->getMessageTemplate();
                    $parameters = $violation->getMessageParameters();
                    $pluralization = $violation->getMessagePluralization();
                    $error = new FormError($template, $parameters, $pluralization);

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

    private function buildFormPathMapping(FormInterface $form, array &$mapping, $formPath = 'children', $namePath = '')
    {
        foreach ($form->getAttribute('error_mapping') as $nestedDataPath => $nestedNamePath) {
            $mapping['/^'.preg_quote($formPath.'.data.'.$nestedDataPath).'(?!\w)/'] = $namePath.'.'.$nestedNamePath;
        }

        $iterator = new VirtualFormAwareIterator($form->getChildren());
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $child) {
            $path = (string) $child->getAttribute('property_path');
            $parts = explode('.', $path, 2);

            $nestedNamePath = $namePath.'.'.$child->getName();

            if ($child->hasChildren() || isset($parts[1])) {
                $nestedFormPath = $formPath.'['.trim($parts[0], '[]').']';
            } else {
                $nestedFormPath = $formPath.'.data.'.$parts[0];
            }

            if (isset($parts[1])) {
                $nestedFormPath .= '.data.'.$parts[1];
            }

            if ($child->hasChildren()) {
                $this->buildFormPathMapping($child, $mapping, $nestedFormPath, $nestedNamePath);
            }

            $mapping['/^'.preg_quote($nestedFormPath, '/').'(?!\w)/'] = $child;
        }
    }

    private function buildDataPathMapping(FormInterface $form, array &$mapping, $dataPath = 'data', $namePath = '')
    {
        foreach ($form->getAttribute('error_mapping') as $nestedDataPath => $nestedNamePath) {
            $mapping['/^'.preg_quote($dataPath.'.'.$nestedDataPath).'(?!\w)/'] = $namePath.'.'.$nestedNamePath;
        }

        $iterator = new VirtualFormAwareIterator($form->getChildren());
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $child) {
            $path = (string) $child->getAttribute('property_path');

            $nestedNamePath = $namePath.'.'.$child->getName();

            if (0 === strpos($path, '[')) {
                $nestedDataPaths = array($dataPath.$path);
            } else {
                $nestedDataPaths = array($dataPath.'.'.$path);
                if ($child->hasChildren()) {
                    $nestedDataPaths[] = $dataPath.'['.$path.']';
                }
            }

            if ($child->hasChildren()) {
                // Needs when collection implements the Iterator
                // or for array used the Valid validator.
                if (is_array($child->getData()) || $child->getData() instanceof \Traversable) {
                    $this->buildDataPathMapping($child, $mapping, $dataPath, $nestedNamePath);
                }

                foreach ($nestedDataPaths as $nestedDataPath) {
                    $this->buildDataPathMapping($child, $mapping, $nestedDataPath, $nestedNamePath);
                }
            }

            foreach ($nestedDataPaths as $nestedDataPath) {
                $mapping['/^'.preg_quote($nestedDataPath, '/').'(?!\w)/'] = $child;
            }
        }
    }

    private function buildNamePathMapping(FormInterface $form, array &$forms, $namePath = '')
    {
        $iterator = new VirtualFormAwareIterator($form->getChildren());
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $child) {
            $nestedNamePath = $namePath.'.'.$child->getName();
            $forms[$nestedNamePath] = $child;

            if ($child->hasChildren()) {
                $this->buildNamePathMapping($child, $forms, $nestedNamePath);
            }

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
