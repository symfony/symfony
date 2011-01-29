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
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Default implementaton of FormContextInterface
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FormContext implements FormContextInterface
{
    /**
     * The locale used by new forms
     * @var string
     */
    protected static $locale = 'en';

    /**
     * The validator used in the new form
     * @var ValidatorInterface
     */
    protected $validator = null;

    /**
     * The validation group(s) validated in the new form
     * @var string|array
     */
    protected $validationGroups = null;

    /**
     * The field factory used for automatically creating fields in the form
     * @var FieldFactoryInterface
     */
    protected $fieldFactory = null;

    /**
     * The provider used to generate and validate CSRF tokens
     * @var array
     */
    protected $csrfProvider = null;

    /**
     * Whether the new form should be CSRF protected
     * @var Boolean
     */
    protected $csrfProtection = false;

    /**
     * The field name used for the CSRF protection
     * @var string
     */
    protected $csrfFieldName = '_token';

    /**
     * Globally sets the locale for new forms and fields
     *
     * @param string $locale  A valid locale, such as "en", "de_DE" etc.
     */
    public static function setLocale($locale)
    {
        self::$locale = $locale;
    }

    /**
     * Returns the locale used for new forms and fields
     *
     * @return string  A valid locale, such as "en", "de_DE" etc.
     */
    public static function getLocale()
    {
        return self::$locale;
    }

    /**
     * @inheritDoc
     */
    public function validator(ValidatorInterface $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validationGroups($validationGroups)
    {
        $this->validationGroups = null === $validationGroups ? $validationGroups : (array) $validationGroups;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fieldFactory(FieldFactoryInterface $fieldFactory)
    {
        $this->fieldFactory = $fieldFactory;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function csrfProtection($enabled)
    {
        $this->csrfProtection = $enabled;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function csrfFieldName($name)
    {
        $this->csrfFieldName = $name;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function csrfProvider(CsrfProviderInterface $csrfProvider)
    {
        $this->csrfProvider = $csrfProvider;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getForm($name, $data = null)
    {
        $form = new Form(
            $name,
            array(
                'validator' => $this->validator,
                'csrf_field_name' => $this->csrfFieldName,
                'csrf_provider' => $this->csrfProtection ? $this->csrfProvider : null,
                'validation_groups' => $this->validationGroups,
                'field_factory' => $this->fieldFactory,
            )
        );
        $form->setData($data);

        return $form;
    }

    /**
     * Returns the validator used in the new form
     *
     * @return ValidatorInterface  The validator instance
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Returns the validation groups validated by the new form
     *
     * @return string|array  One or more validation groups
     */
    public function getValidationGroups()
    {
        return $this->validationGroups;
    }

    /**
     * Returns the field factory used by the new form
     *
     * @return FieldFactoryInterface  The field factory instance
     */
    public function getFieldFactory()
    {
        return $this->fieldFactory;
    }

    /**
     * Returns whether the new form will be CSRF protected
     *
     * @return Boolean  Whether the form will be CSRF protected
     */
    public function isCsrfProtectionEnabled()
    {
        return $this->csrfProtection;
    }

    /**
     * Returns the field name used for CSRF protection in the new form
     *
     * @return string  The CSRF field name
     */
    public function getCsrfFieldName()
    {
        return $this->csrfFieldName;
    }

    /**
     * Returns the CSRF provider used to generate and validate CSRF tokens
     *
     * @return CsrfProviderInterface  The provider instance
     */
    public function getCsrfProvider()
    {
        return $this->csrfProvider;
    }
}
