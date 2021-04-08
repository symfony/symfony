<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Constraints;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormValidator extends ConstraintValidator
{
    private $resolvedGroups;

    /**
     * {@inheritdoc}
     */
    public function validate($form, Constraint $formConstraint)
    {
        if (!$formConstraint instanceof Form) {
            throw new UnexpectedTypeException($formConstraint, Form::class);
        }

        if (!$form instanceof FormInterface) {
            return;
        }

        /* @var FormInterface $form */
        $config = $form->getConfig();

        $validator = $this->context->getValidator()->inContext($this->context);

        if ($form->isSubmitted() && $form->isSynchronized()) {
            // Validate the form data only if transformation succeeded
            $groups = $this->getValidationGroups($form);

            if (!$groups) {
                return;
            }

            $data = $form->getData();
            // Validate the data against its own constraints
            $validateDataGraph = $form->isRoot()
                && (\is_object($data) || \is_array($data))
                && (($groups && \is_array($groups)) || ($groups instanceof GroupSequence && $groups->groups))
            ;

            // Validate the data against the constraints defined in the form
            /** @var Constraint[] $constraints */
            $constraints = $config->getOption('constraints', []);

            $hasChildren = $form->count() > 0;

            if ($hasChildren && $form->isRoot()) {
                $this->resolvedGroups = new \SplObjectStorage();
            }

            if ($groups instanceof GroupSequence) {
                // Validate the data, the form AND nested fields in sequence
                $violationsCount = $this->context->getViolations()->count();

                foreach ($groups->groups as $group) {
                    if ($validateDataGraph) {
                        $validator->atPath('data')->validate($data, null, $group);
                    }

                    if ($groupedConstraints = self::getConstraintsInGroups($constraints, $group)) {
                        $validator->atPath('data')->validate($data, $groupedConstraints, $group);
                    }

                    foreach ($form->all() as $field) {
                        if ($field->isSubmitted()) {
                            // remember to validate this field in one group only
                            // otherwise resolving the groups would reuse the same
                            // sequence recursively, thus some fields could fail
                            // in different steps without breaking early enough
                            $this->resolvedGroups[$field] = (array) $group;
                            $fieldFormConstraint = new Form();
                            $fieldFormConstraint->groups = $group;
                            $this->context->setNode($this->context->getValue(), $field, $this->context->getMetadata(), $this->context->getPropertyPath());
                            $validator->atPath(sprintf('children[%s]', $field->getName()))->validate($field, $fieldFormConstraint, $group);
                        }
                    }

                    if ($violationsCount < $this->context->getViolations()->count()) {
                        break;
                    }
                }
            } else {
                if ($validateDataGraph) {
                    $validator->atPath('data')->validate($data, null, $groups);
                }

                $groupedConstraints = [];

                foreach ($constraints as $constraint) {
                    // For the "Valid" constraint, validate the data in all groups
                    if ($constraint instanceof Valid) {
                        if (\is_object($data)) {
                            $validator->atPath('data')->validate($data, $constraint, $groups);
                        }

                        continue;
                    }

                    // Otherwise validate a constraint only once for the first
                    // matching group
                    foreach ($groups as $group) {
                        if (\in_array($group, $constraint->groups)) {
                            $groupedConstraints[$group][] = $constraint;

                            // Prevent duplicate validation
                            if (!$constraint instanceof Composite) {
                                continue 2;
                            }
                        }
                    }
                }

                foreach ($groupedConstraints as $group => $constraint) {
                    $validator->atPath('data')->validate($data, $constraint, $group);
                }

                foreach ($form->all() as $field) {
                    if ($field->isSubmitted()) {
                        $this->resolvedGroups[$field] = $groups;
                        $this->context->setNode($this->context->getValue(), $field, $this->context->getMetadata(), $this->context->getPropertyPath());
                        $validator->atPath(sprintf('children[%s]', $field->getName()))->validate($field, $formConstraint);
                    }
                }
            }

            if ($hasChildren && $form->isRoot()) {
                // destroy storage to avoid memory leaks
                $this->resolvedGroups = new \SplObjectStorage();
            }
        } elseif (!$form->isSynchronized()) {
            $childrenSynchronized = true;

            /** @var FormInterface $child */
            foreach ($form as $child) {
                if (!$child->isSynchronized()) {
                    $childrenSynchronized = false;
                    $this->context->setNode($this->context->getValue(), $child, $this->context->getMetadata(), $this->context->getPropertyPath());
                    $validator->atPath(sprintf('children[%s]', $child->getName()))->validate($child, $formConstraint);
                }
            }

            // Mark the form with an error if it is not synchronized BUT all
            // of its children are synchronized. If any child is not
            // synchronized, an error is displayed there already and showing
            // a second error in its parent form is pointless, or worse, may
            // lead to duplicate errors if error bubbling is enabled on the
            // child.
            // See also https://github.com/symfony/symfony/issues/4359
            if ($childrenSynchronized) {
                $clientDataAsString = is_scalar($form->getViewData())
                    ? (string) $form->getViewData()
                    : get_debug_type($form->getViewData());

                $failure = $form->getTransformationFailure();

                $this->context->setConstraint($formConstraint);
                $this->context->buildViolation($failure->getInvalidMessage() ?? $config->getOption('invalid_message'))
                    ->setParameters(array_replace(
                        ['{{ value }}' => $clientDataAsString],
                        $config->getOption('invalid_message_parameters'),
                        $failure->getInvalidMessageParameters()
                    ))
                    ->setInvalidValue($form->getViewData())
                    ->setCode(Form::NOT_SYNCHRONIZED_ERROR)
                    ->setCause($failure)
                    ->addViolation();
            }
        }

        // Mark the form with an error if it contains extra fields
        if (!$config->getOption('allow_extra_fields') && \count($form->getExtraData()) > 0) {
            $this->context->setConstraint($formConstraint);
            $this->context->buildViolation($config->getOption('extra_fields_message', ''))
                ->setParameter('{{ extra_fields }}', '"'.implode('", "', array_keys($form->getExtraData())).'"')
                ->setPlural(\count($form->getExtraData()))
                ->setInvalidValue($form->getExtraData())
                ->setCode(Form::NO_SUCH_FIELD_ERROR)
                ->addViolation();
        }
    }

    /**
     * Returns the validation groups of the given form.
     *
     * @return string|GroupSequence|array<string|GroupSequence> The validation groups
     */
    private function getValidationGroups(FormInterface $form)
    {
        // Determine the clicked button of the complete form tree
        $clickedButton = null;

        if (method_exists($form, 'getClickedButton')) {
            $clickedButton = $form->getClickedButton();
        }

        if (null !== $clickedButton) {
            $groups = $clickedButton->getConfig()->getOption('validation_groups');

            if (null !== $groups) {
                return self::resolveValidationGroups($groups, $form);
            }
        }

        do {
            $groups = $form->getConfig()->getOption('validation_groups');

            if (null !== $groups) {
                return self::resolveValidationGroups($groups, $form);
            }

            if (isset($this->resolvedGroups[$form])) {
                return $this->resolvedGroups[$form];
            }

            $form = $form->getParent();
        } while (null !== $form);

        return [Constraint::DEFAULT_GROUP];
    }

    /**
     * Post-processes the validation groups option for a given form.
     *
     * @param string|GroupSequence|array<string|GroupSequence>|callable $groups The validation groups
     *
     * @return GroupSequence|array<string|GroupSequence> The validation groups
     */
    private static function resolveValidationGroups($groups, FormInterface $form)
    {
        if (!\is_string($groups) && \is_callable($groups)) {
            $groups = $groups($form);
        }

        if ($groups instanceof GroupSequence) {
            return $groups;
        }

        return (array) $groups;
    }

    private static function getConstraintsInGroups($constraints, $group)
    {
        return array_filter($constraints, static function (Constraint $constraint) use ($group) {
            return \in_array($group, $constraint->groups, true);
        });
    }
}
