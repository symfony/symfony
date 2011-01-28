<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;

/**
 * Form represents a form.
 *
 * A form is composed of a validator schema and a widget form schema.
 *
 * Form also takes care of Csrf protection by default.
 *
 * A Csrf secret can be any random string. If set to false, it disables the
 * Csrf protection, and if set to null, it forces the form to use the global
 * Csrf secret. If the global Csrf secret is also null, then a random one
 * is generated on the fly.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class Form extends FieldGroup
{
    /**
     * The validator to validate form values
     * @var ValidatorInterface
     */
    protected $validator = null;

    /**
     * Constructor.
     *
     * @param string $name
     * @param array|object $data
     * @param ValidatorInterface $validator
     * @param array $options
     */
    public function __construct($name = null, $data = null, ValidatorInterface $validator = null, array $options = array())
    {
        $this->validator = $validator;

        // Prefill the form with the given data
        if (null !== $data) {
            $this->setData($data);
        }

        $this->addOption('csrf_field_name', '_token');
        $this->addOption('csrf_provider');
        $this->addOption('field_factory');
        $this->addOption('validation_groups');

        if (isset($options['validation_groups'])) {
            $options['validation_groups'] = (array)$options['validation_groups'];
        }

        parent::__construct($name, $options);

        // If data is passed to this constructor, objects from parent forms
        // should be ignored
        if (null !== $data) {
            $this->setPropertyPath(null);
        }

        // Enable CSRF protection, if necessary
        if ($this->getOption('csrf_provider')) {
            if (!$this->getOption('csrf_provider') instanceof CsrfProviderInterface) {
                throw new FormException('The object passed to the "csrf_provider" option must implement CsrfProviderInterface');
            }

            $token = $this->getOption('csrf_provider')->generateCsrfToken(get_class($this));

            $field = new HiddenField($this->getOption('csrf_field_name'), array(
                'property_path' => null,
            ));
            $field->setData($token);

            $this->add($field);
        }
    }

    /**
     * Returns a factory for automatically creating fields based on metadata
     * available for a form's object
     *
     * @return FieldFactoryInterface  The factory
     */
    public function getFieldFactory()
    {
        return $this->getOption('field_factory');
    }

    /**
     * Returns the validator used by the form
     *
     * @return ValidatorInterface  The validator instance
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Returns the validation groups validated by the form
     *
     * @return array  A list of validation groups or null
     */
    public function getValidationGroups()
    {
        return $this->getOption('validation_groups');
    }

    /**
     * Returns the name used for the CSRF protection field
     *
     * @return string  The field name
     */
    public function getCsrfFieldName()
    {
        return $this->getOption('csrf_field_name');
    }

    /**
     * Returns the provider used for generating and validating CSRF tokens
     *
     * @return CsrfProviderInterface  The provider instance
     */
    public function getCsrfProvider()
    {
        return $this->getOption('csrf_provider');
    }

    /**
     * Binds the form with values and files.
     *
     * This method is final because it is very easy to break a form when
     * overriding this method and adding logic that depends on $taintedFiles.
     * You should override doBind() instead where the uploaded files are
     * already merged into the data array.
     *
     * @param  array $taintedValues  The form data of the $_POST array
     * @param  array $taintedFiles   An array of uploaded files
     * @return Boolean               Whether the form is valid
     */
    final public function bind($taintedValues, array $taintedFiles = null)
    {
        if (null === $taintedFiles) {
            if ($this->isMultipart() && $this->getParent() === null) {
                throw new \InvalidArgumentException('You must provide a files array for multipart forms');
            }

            $taintedFiles = array();
        }

        if (null === $taintedValues) {
            $taintedValues = array();
        }

        $this->doBind(self::deepArrayUnion($taintedValues, $taintedFiles));

        if ($this->getParent() === null) {
            if ($this->validator === null) {
                throw new FormException('A validator is required for binding. Forgot to pass it to the constructor of the form?');
            }

            if ($violations = $this->validator->validate($this, $this->getOption('validation_groups'))) {
                // TODO: test me
                foreach ($violations as $violation) {
                    $propertyPath = new PropertyPath($violation->getPropertyPath());
                    $iterator = $propertyPath->getIterator();

                    if ($iterator->current() == 'data') {
                        $type = self::DATA_ERROR;
                        $iterator->next(); // point at the first data element
                    } else {
                        $type = self::FIELD_ERROR;
                    }

                    $this->addError(new FieldError($violation->getMessageTemplate(), $violation->getMessageParameters()), $iterator, $type);
                }
            }
        }
    }

    /**
     * Binds the form with the given data.
     *
     * @param  array $taintedData  The data to bind to the form
     * @return Boolean             Whether the form is valid
     */
    protected function doBind(array $taintedData)
    {
        parent::bind($taintedData);
    }

    /**
     * @return true if this form is CSRF protected
     */
    public function isCsrfProtected()
    {
        return $this->has($this->getOption('csrf_field_name'));
    }

    /**
     * Returns whether the CSRF token is valid
     *
     * @return Boolean
     */
    public function isCsrfTokenValid()
    {
        if (!$this->isCsrfProtected()) {
            return true;
        } else {
            $token = $this->get($this->getOption('csrf_field_name'))->getDisplayedData();

            return $this->getOption('csrf_provider')->isCsrfTokenValid(get_class($this), $token);
        }
    }

    /**
     * Returns whether the maximum POST size was reached in this request.
     *
     * @return Boolean
     */
    public function isPostMaxSizeReached()
    {
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $length = (int) $_SERVER['CONTENT_LENGTH'];
            $max = trim(ini_get('post_max_size'));

            switch (strtolower(substr($max, -1))) {
                // The 'G' modifier is available since PHP 5.1.0
                case 'g':
                    $max *= 1024;
                case 'm':
                    $max *= 1024;
                case 'k':
                    $max *= 1024;
            }

            return $length > $max;
        } else {
            return false;
        }
    }

    /**
     * Merges two arrays without reindexing numeric keys.
     *
     * @param array $array1 An array to merge
     * @param array $array2 An array to merge
     *
     * @return array The merged array
     */
    static protected function deepArrayUnion($array1, $array2)
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::deepArrayUnion($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
