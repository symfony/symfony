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
        if (null === $params) {
            $params = new ServerParams();
        }

        $this->serverParams = $params;
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

        $path = $this->context->getPropertyPath();
        $graphWalker = $this->context->getGraphWalker();
        $groups = $this->getValidationGroups($form);

        if (!empty($path)) {
            $path .= '.';
        }

        if ($form->isSynchronized()) {
            // Validate the form data only if transformation succeeded

            // Validate the data against its own constraints
            if (self::allowDataWalking($form)) {
                foreach ($groups as $group) {
                    $graphWalker->walkReference($form->getData(), $group, $path . 'data', true);
                }
            }

            // Validate the data against the constraints defined
            // in the form
            $constraints = $form->getAttribute('constraints');
            foreach ($constraints as $constraint) {
                foreach ($groups as $group) {
                    $graphWalker->walkConstraint($constraint, $form->getData(), $group, $path . 'data');
                }
            }
        } else {
            $clientDataAsString = is_scalar($form->getClientData())
                ? (string) $form->getClientData()
                : gettype($form->getClientData());

            // Mark the form with an error if it is not synchronized
            $this->context->addViolation(
                $form->getAttribute('invalid_message'),
                array('{{ value }}' => $clientDataAsString),
                $form->getClientData(),
                null,
                Form::ERR_INVALID
            );
        }

        // Mark the form with an error if it contains extra fields
        if (count($form->getExtraData()) > 0) {
            $this->context->addViolation(
                $form->getAttribute('extra_fields_message'),
                array('{{ extra_fields }}' => implode('", "', array_keys($form->getExtraData()))),
                $form->getExtraData()
            );
        }

        // Mark the form with an error if the uploaded size was too large
        $length = $this->serverParams->getContentLength();

        if ($form->isRoot() && null !== $length) {
            $max = strtoupper(trim($this->serverParams->getPostMaxSize()));

            if ('' !== $max) {
                $maxLength = (int) $max;

                switch (substr($max, -1)) {
                    // The 'G' modifier is available since PHP 5.1.0
                    case 'G':
                        $maxLength *= pow(1024, 3);
                        break;
                    case 'M':
                        $maxLength *= pow(1024, 2);
                        break;
                    case 'K':
                        $maxLength *= 1024;
                        break;
                }

                if ($length > $maxLength) {
                    $this->context->addViolation(
                        $form->getAttribute('post_max_size_message'),
                        array('{{ max }}' => $max),
                        $length
                    );
                }
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
    private function allowDataWalking(FormInterface $form)
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
        $parent = $form->getParent();

        while (null !== $parent) {
            if (!$parent->getAttribute('cascade_validation')) {
                return false;
            }

            $parent = $parent->getParent();
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
    private function getValidationGroups(FormInterface $form)
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
}
