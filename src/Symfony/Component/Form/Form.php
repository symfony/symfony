<?php

namespace Symfony\Component\Form;

use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\I18N\TranslatorInterface;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @version    SVN: $Id: Form.php 245 2010-01-31 22:22:39Z flo $
 */
class Form extends FieldGroup
{
    protected static $defaultCsrfSecret = null;
    protected static $defaultCsrfProtection = false;
    protected static $defaultCsrfFieldName = '_token';
    protected static $defaultLocale = null;
    protected static $defaultTranslator = null;

    protected $validator = null;
    protected $validationGroups = null;

    private $csrfSecret = null;
    private $csrfFieldName = null;

    /**
     * Constructor.
     *
     * @param array  $defaults    An array of field default values
     * @param array  $options     An array of options
     * @param string $defaultCsrfSecret  A Csrf secret
     */
    public function __construct($name, $object, ValidatorInterface $validator, array $options = array())
    {
        $this->generator = new HtmlGenerator();
        $this->validator = $validator;

        $this->setData($object);
        $this->setCsrfFieldName(self::$defaultCsrfFieldName);

        if (self::$defaultCsrfSecret !== null) {
            $this->setCsrfSecret(self::$defaultCsrfSecret);
        } else {
            $this->setCsrfSecret(md5(__FILE__.php_uname()));
        }

        if (self::$defaultCsrfProtection !== false) {
            $this->enableCsrfProtection();
        }

        if (self::$defaultLocale !== null) {
            $this->setLocale(self::$defaultLocale);
        }

        if (self::$defaultTranslator !== null) {
            $this->setTranslator(self::$defaultTranslator);
        }

        parent::__construct($name, $options);
    }

    /**
     * Sets the charset used for rendering HTML
     *
     * This method overrides the internal HTML generator! If you want to use
     * your own generator, use setGenerator() instead.
     *
     * @param string $charset
     */
    public function setCharset($charset)
    {
        $this->setGenerator(new HtmlGenerator($charset));
    }

    /**
     * Sets the validation groups for this form.
     *
     * @param array|string $validationGroups
     */
    public function setValidationGroups($validationGroups)
    {
        $this->validationGroups = $validationGroups === null ? $validationGroups : (array) $validationGroups;
    }

    /**
     * Returns the validation groups for this form.
     *
     * @return array
     */
    public function getValidationGroups()
    {
        return $this->validationGroups;
    }

    /**
     * Sets the default locale for newly created forms.
     *
     * @param string $defaultLocale
     */
    static public function setDefaultLocale($defaultLocale)
    {
        self::$defaultLocale = $defaultLocale;
    }

    /**
     * Returns the default locale for newly created forms.
     *
     * @return string
     */
    static public function getDefaultLocale()
    {
        return self::$defaultLocale;
    }

    /**
     * Sets the default translator for newly created forms.
     *
     * @param TranslatorInterface $defaultTranslator
     */
    static public function setDefaultTranslator(TranslatorInterface $defaultTranslator)
    {
        self::$defaultTranslator = $defaultTranslator;
    }

    /**
     * Returns the default translator for newly created forms.
     *
     * @return TranslatorInterface
     */
    static public function getDefaultTranslator()
    {
        return self::$defaultTranslator;
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
     * @return boolean               Whether the form is valid
     */
    final public function bind($taintedValues, array $taintedFiles = null)
    {
        if ($taintedFiles === null) {
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
            if ($violations = $this->validator->validate($this, $this->getValidationGroups())) {
                foreach ($violations as $violation) {
                    $propertyPath = new PropertyPath($violation->getPropertyPath());

                    if ($propertyPath->getCurrent() == 'data') {
                        $type = self::DATA_ERROR;
                        $propertyPath->next(); // point at the first data element
                    } else {
                        $type = self::FIELD_ERROR;
                    }

                    $this->addError($violation->getMessage(), $propertyPath, $type);
                }
            }
        }
    }

    /**
     * Binds the form with the given data.
     *
     * @param  array $taintedData  The data to bind to the form
     * @return boolean             Whether the form is valid
     */
    protected function doBind(array $taintedData)
    {
        parent::bind($taintedData);
    }

    /**
     * Gets the stylesheet paths associated with the form.
     *
     * @return array An array of stylesheet paths
     */
    public function getStylesheets()
    {
        return $this->getWidget()->getStylesheets();
    }

    /**
     * Gets the JavaScript paths associated with the form.
     *
     * @return array An array of JavaScript paths
     */
    public function getJavaScripts()
    {
        return $this->getWidget()->getJavaScripts();
    }

    /**
     * Returns a CSRF token for the set CSRF secret
     *
     * If you want to change the algorithm used to compute the token, you
     * can override this method.
     *
     * @param  string $secret The secret string to use (null to use the current secret)
     *
     * @return string A token string
     */
    protected function getCsrfToken()
    {
        return md5($this->csrfSecret.session_id().get_class($this));
    }

    /**
     * @return true if this form is CSRF protected
     */
    public function isCsrfProtected()
    {
        return $this->has($this->getCsrfFieldName());
    }

    /**
     * Enables CSRF protection for this form.
     */
    public function enableCsrfProtection()
    {
        if (!$this->isCsrfProtected()) {
            $field = new HiddenField($this->getCsrfFieldName(), array(
                'property_path' => null,
            ));
            $field->setData($this->getCsrfToken());
            $this->add($field);
        }
    }

    /**
     * Disables CSRF protection for this form.
     */
    public function disableCsrfProtection()
    {
        if ($this->isCsrfProtected()) {
            $this->remove($this->getCsrfFieldName());
        }
    }

    /**
     * Sets the CSRF field name used in this form
     *
     * @param string $name The CSRF field name
     */
    public function setCsrfFieldName($name)
    {
        $this->csrfFieldName = $name;
    }

    /**
     * Returns the CSRF field name used in this form
     *
     * @return string The CSRF field name
     */
    public function getCsrfFieldName()
    {
        return $this->csrfFieldName;
    }

    /**
     * Sets the CSRF secret used in this form
     *
     * @param string $secret
     */
    public function setCsrfSecret($secret)
    {
        $this->csrfSecret = $secret;
    }

    /**
     * Returns the CSRF secret used in this form
     *
     * @return string
     */
    public function getCsrfSecret()
    {
        return $this->csrfSecret;
    }

    /**
     * Returns whether the CSRF token is valid
     *
     * @return boolean
     */
    public function isCsrfTokenValid()
    {
        if (!$this->isCsrfProtected()) {
            return true;
        } else {
            return $this->get($this->getCsrfFieldName())->getDisplayedData() === $this->getCsrfToken();
        }
    }

    /**
     * Enables CSRF protection for all new forms
     */
    static public function enableDefaultCsrfProtection()
    {
        self::$defaultCsrfProtection = true;
    }

    /**
     * Disables Csrf protection for all forms.
     */
    static public function disableDefaultCsrfProtection()
    {
        self::$defaultCsrfProtection = false;
    }

    /**
     * Sets the CSRF field name used in all new CSRF protected forms
     *
     * @param string $name The CSRF field name
     */
    static public function setDefaultCsrfFieldName($name)
    {
        self::$defaultCsrfFieldName = $name;
    }

    /**
     * Returns the default CSRF field name
     *
     * @return string The CSRF field name
     */
    static public function getDefaultCsrfFieldName()
    {
        return self::$defaultCsrfFieldName;
    }

    /**
     * Sets the CSRF secret used in all new CSRF protected forms
     *
     * @param string $secret
     */
    static public function setDefaultCsrfSecret($secret)
    {
        self::$defaultCsrfSecret = $secret;
    }

    /**
     * Returns the default CSRF secret
     *
     * @return string
     */
    static public function getDefaultCsrfSecret()
    {
        return self::$defaultCsrfSecret;
    }

    /**
     * Renders the form tag.
     *
     * This method only renders the opening form tag.
     * You need to close it after the form rendering.
     *
     * This method takes into account the multipart widgets.
     *
     * @param  string $url         The URL for the action
     * @param  array  $attributes  An array of HTML attributes
     *
     * @return string An HTML representation of the opening form tag
     */
    public function renderFormTag($url, array $attributes = array())
    {
        return sprintf('<form%s>', $this->generator->attributes(array_merge(array(
            'action' => $url,
            'method' => isset($attributes['method']) ? strtolower($attributes['method']) : 'post',
            'enctype' => $this->isMultipart() ? 'multipart/form-data' : null,
        ), $attributes)));
    }

    /**
     * Returns whether the maximum POST size was reached in this request.
     *
     * @return boolean
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
