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
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Creates and configures new form objects
 *
 * The default configuration of form objects can be passed to the constructor
 * as a FormContextInterface object. Call getForm() to create new form objects.
 *
 * <code>
 * $defaultContext = new FormContext();
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
     * Builds a form factory with default values
     *
     * By default, CSRF protection is enabled. In this case you have to provide
     * a CSRF secret in the second parameter of this method. A recommended
     * value is a generated value with at least 32 characters and mixed
     * letters, digits and special characters.
     *
     * If you don't want to use CSRF protection, you can leave the CSRF secret
     * empty and set the third parameter to false.
     *
     * @param ValidatorInterface $validator  The validator for validating
     *                                       forms
     * @param string $csrfSecret             The secret to be used for
     *                                       generating CSRF tokens
     * @param boolean $csrfProtection        Whether forms should be CSRF
     *                                       protected
     * @throws FormException                 When CSRF protection is enabled,
     *                                       but no CSRF secret is passed
     */
    public static function buildDefault(ValidatorInterface $validator, $csrfSecret = null, $csrfProtection = true)
    {
        $context = new FormContext();
        $context->csrfProtection($csrfProtection);
        $context->validator($validator);

        if ($csrfProtection) {
            if (empty($csrfSecret)) {
                throw new FormException('Please provide a CSRF secret when CSRF protection is enabled');
            }

            $context->csrfProvider(new DefaultCsrfProvider($csrfSecret));
        }

        return new static($context);
    }

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
     * Overrides the CSRF provider setting of the default context and returns
     * the new context
     *
     * @param  CsrfProviderInterface $provider  The provider instance
     * @return FormContextInterface             The preconfigured form context
     */
    public function csrfProvider(CsrfProviderInterface $csrfProvider)
    {
        $context = clone $this->defaultContext;

        return $context->csrfProvider($csrfProvider);
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