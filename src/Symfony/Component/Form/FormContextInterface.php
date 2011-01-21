<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Stores settings for creating a new form and creates forms
 *
 * The methods in this class are chainable, i.e. they return the form context
 * object itself. When you have finished configuring the new form, call
 * getForm() to create the form.
 *
 * <code>
 * $form = $context
 *     ->locale('en_US')
 *     ->validationGroups('Address')
 *     ->getForm('author');
 * </code>
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FormContextInterface
{
    /**
     * Sets the validator used for validating the form
     *
     * @param  ValidatorInterface $validator  The validator instance
     * @return FormContextInterface           This object
     */
    function validator(ValidatorInterface $validator);

    /**
     * Sets the validation groups validated by the form
     *
     * @param  string|array $validationGroups  One or more validation groups
     * @return FormContextInterface            This object
     */
    function validationGroups($validationGroups);

    /**
     * Sets the field factory used for automatically creating fields in the form
     *
     * @param  FieldFactoryInterface $fieldFactory  The field factory instance
     * @return FormContextInterface                 This object
     */
    function fieldFactory(FieldFactoryInterface $fieldFactory);

    /**
     * Enables or disables CSRF protection for the  form
     *
     * @param Boolean $enabled       Whether the form should be CSRF protected
     * @return FormContextInterface  This object
     */
    function csrfProtection($enabled);

    /**
     * Sets the field name used for CSRF protection in the form
     *
     * @param  string $name          The CSRF field name
     * @return FormContextInterface  This object
     */
    function csrfFieldName($name);

    /**
     * Sets the CSRF secrets to be used in the form
     *
     * @param  array $secrets        A list of secret values
     * @return FormContextInterface  This object
     */
    function csrfSecrets(array $secrets);

    /**
     * Adds another CSRF secrets without removing the existing CSRF secrets
     *
     * @param  string $secret        A secret value
     * @return FormContextInterface  This object
     * @see csrfSecrets()
     */
    function addCsrfSecret($secret);

    /**
     * Creates a new form with the settings stored in this context
     *
     * @param  string $name        The name for the form
     * @param  array|object $data  The data displayed and modified by the form
     * @return Form                The new form
     */
    function getForm($name, $data = null);
}