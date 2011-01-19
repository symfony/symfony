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
 * Creates and configures new form objects
 *
 * The default configuration of form objects can be passed to the constructor
 * as a FormContextInterface object. Call getForm() to create new form objects.
 *
 * <code>
 * $defaultContext = new FormContext();
 * $defaultContext->locale('en_US');
 * $defaultContext->csrfProtection(true);
 * $factory = new FormFactory($defaultContext);
 *
 * $form = $factory->getForm('author');
 * </code>
 *
 * You can also override the default configuration by calling any of the
 * methods in this class. These methods return a FormContextInterface object
 * on which you can override further settings or call getForm() to create
 * a form.
 *
 * <code>
 * $form = $factory
 *     ->locale('de_DE')
 *     ->csrfProtection(false)
 *     ->getForm('author');
 * </code>
 *
 * FormFactory instances should be cached and reused in your application.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FormFactory implements FormContextInterface
{
    /**
     * Holds the context with the default configuration
     * @var FormContextInterface
     */
    protected $defaultContext;

    /**
     * Sets the given context as default context
     *
     * @param FormContextInterface $defaultContext  A preconfigured context
     */
    public function __construct(FormContextInterface $defaultContext = null)
    {
        $this->defaultContext = null === $defaultContext ? new FormContext() : $defaultContext;
    }

    /**
     * Overrides the validator of the default context and returns the new context
     *
     * @param  ValidatorInterface $validator  The new validator instance
     * @return FormContextInterface           The preconfigured form context
     */
    public function validator(ValidatorInterface $validator)
    {
        $context = clone $this->defaultContext;

        return $context->validator($validator);
    }

    /**
     * Overrides the validation groups of the default context and returns
     * the new context
     *
     * @param  string|array $validationGroups  One or more validation groups
     * @return FormContextInterface            The preconfigured form context
     */
    public function validationGroups($validationGroups)
    {
        $context = clone $this->defaultContext;

        return $context->validationGroups($validationGroups);
    }

    /**
     * Overrides the field factory of the default context and returns
     * the new context
     *
     * @param  FieldFactoryInterface $fieldFactory  A field factory instance
     * @return FormContextInterface                 The preconfigured form context
     */
    public function fieldFactory(FieldFactoryInterface $fieldFactory)
    {
        $context = clone $this->defaultContext;

        return $context->fieldFactory($fieldFactory);
    }

    /**
     * Overrides the CSRF protection setting of the default context and returns
     * the new context
     *
     * @param  boolean $enabled      Whether the form should be CSRF protected
     * @return FormContextInterface  The preconfigured form context
     */
    public function csrfProtection($enabled)
    {
        $context = clone $this->defaultContext;

        return $context->csrfProtection($enabled);
    }

    /**
     * Overrides the CSRF field name setting of the default context and returns
     * the new context
     *
     * @param  string $name          The field name to use for CSRF protection
     * @return FormContextInterface  The preconfigured form context
     */
    public function csrfFieldName($name)
    {
        $context = clone $this->defaultContext;

        return $context->csrfFieldName($name);
    }

    /**
     * Overrides the CSRF secrets setting of the default context and returns
     * the new context
     *
     * @param  array $secrets        The secrets to use for CSRF protection
     * @return FormContextInterface  The preconfigured form context
     */
    public function csrfSecrets(array $secrets)
    {
        $context = clone $this->defaultContext;

        return $context->csrfSecrets($secrets);
    }

    /**
     * Adds a new CSRF secret to the ones in the default context and returns
     * the new context
     *
     * @param  string $secret        The secret to add to the secrets used for
     *                               CSRF protection
     * @return FormContextInterface  The preconfigured form context
     */
    public function addCsrfSecret($secret)
    {
        $context = clone $this->defaultContext;

        return $context->addCsrfSecret($secret);
    }

    /**
     * Creates a new form with the settings stored in the default context
     *
     * @param  string $name        The name for the form
     * @param  array|object $data  The data displayed and modified by the form
     * @return Form                The new form
     */
    public function getForm($name, $data = null)
    {
        return $this->defaultContext->getForm($name, $data);
    }
}