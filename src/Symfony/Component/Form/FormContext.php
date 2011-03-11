<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Form\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Default implementation of FormContextInterface
 *
 * This class is immutable by design.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FormContext implements FormContextInterface
{
    /**
     * The options used in new forms
     * @var array
     */
    protected $options = null;

    /**
     * Builds a context with default values
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
        $options = array(
            'csrf_protection' => $csrfProtection,
            'validator' => $validator,
        );

        if ($csrfProtection) {
            if (empty($csrfSecret)) {
                throw new FormException('Please provide a CSRF secret when CSRF protection is enabled');
            }

            $options['csrf_provider'] = new DefaultCsrfProvider($csrfSecret);
        }

        return new static($options);
    }

    /**
     * Constructor
     *
     * Initializes the context with the settings stored in the given
     * options.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if (isset($options['csrf_protection'])) {
            if (!$options['csrf_protection']) {
                // don't include a CSRF provider if CSRF protection is disabled
                unset($options['csrf_provider']);
            }

            unset($options['csrf_protection']);
        }

        $options['context'] = $this;

        $this->options = $options;
    }

    /**
     * {@inheritDoc}
     */
    public function getOptions()
    {
        return $this->options;
    }
}
