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

use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Validator\Util\ServerParams;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormValidator extends ConstraintValidator
{
    /**
     * @var ServerParams
     */
    private $serverParams;

    /**
     * Creates a validator with the given server parameters.
     *
     * @param ServerParams $params The server parameters. Default
     *                             parameters are created if null.
     */
    public function __construct(ServerParams $params = null)
    {
        $this->serverParams = $params ?: new ServerParams();
    }

    /**
     * {@inheritdoc}
     */
    public function validate($form, Constraint $constraint)
    {
        if (!$form instanceof FormInterface) {
            return;
        }

        /* @var FormInterface $form */
        $config = $form->getConfig();

        if ($form->isSynchronized()) {
            // Validate the form data only if transformation succeeded
            $groups = self::getValidationGroups($form);

            // Validate the data against its own constraints
            if (self::allowDataWalking($form)) {
                foreach ($groups as $group) {
                    $this->context->validate($form->getData(), 'data', $group, true);
                }
            }

            // Validate the data against the constraints defined
            // in the form
            $constraints = $config->getOption('constraints');
            foreach ($constraints as $constraint) {
                foreach ($groups as $group) {
                    if (in_array($group, $constraint->groups)) {
                        $this->context->validateValue($form->getData(), $constraint, 'data', $group);

                        // Prevent duplicate validation
                        continue 2;
                    }
                }
            }
        } else {
            $childrenSynchronized = true;

            foreach ($form as $child) {
                if (!$child->isSynchronized()) {
                    $childrenSynchronized = false;
                    break;
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
                    : gettype($form->getViewData());

                $this->context->addViolation(
                    $config->getOption('invalid_message'),
                    array_replace(array('{{ value }}' => $clientDataAsString), $config->getOption('invalid_message_parameters')),
                    $form->getViewData(),
                    null,
                    Form::ERR_INVALID
                );
            }
        }

        // Mark the form with an error if it contains extra fields
        if (count($form->getExtraData()) > 0) {
            $this->context->addViolation(
                $config->getOption('extra_fields_message'),
                array('{{ extra_fields }}' => implode('", "', array_keys($form->getExtraData()))),
                $form->getExtraData()
            );
        }

        // Mark the form with an error if the uploaded size was too large
        $length = $this->serverParams->getContentLength();

        if ($form->isRoot() && null !== $length) {
            $max = $this->serverParams->getPostMaxSize();

            if (!empty($max) && $length > $max) {
                $this->context->addViolation(
                    $config->getOption('post_max_size_message'),
                    array('{{ max }}' => $this->serverParams->getNormalizedIniPostMaxSize()),
                    $length
                );
            }
        }
    }

    /**
     * Returns whether the data of a form may be walked.
     *
     * @param  FormInterface $form The form to test.
     *
     * @return Boolean Whether the graph walker may walk the data.
     */
    private static function allowDataWalking(FormInterface $form)
    {
        $data = $form->getData();

        // Scalar values cannot have mapped constraints
        if (!is_object($data) && !is_array($data)) {
            return false;
        }

        // Root forms are always validated
        if ($form->isRoot()) {
            return true;
        }

        // Non-root forms are validated if validation cascading
        // is enabled in all ancestor forms
        while (null !== ($form = $form->getParent())) {
            if (!$form->getConfig()->getOption('cascade_validation')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the validation groups of the given form.
     *
     * @param  FormInterface $form The form.
     *
     * @return array The validation groups.
     */
    private static function getValidationGroups(FormInterface $form)
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

            $form = $form->getParent();
        } while (null !== $form);

        return array(Constraint::DEFAULT_GROUP);
    }

    /**
     * Post-processes the validation groups option for a given form.
     *
     * @param array|callable $groups The validation groups.
     * @param FormInterface  $form   The validated form.
     *
     * @return array The validation groups.
     */
    private static function resolveValidationGroups($groups, FormInterface $form)
    {
        if (!is_string($groups) && is_callable($groups)) {
            $groups = call_user_func($groups, $form);
        }

        return (array) $groups;
    }
}
