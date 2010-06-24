<?php

namespace Symfony\Components\Form\ValueTransformer;

use Symfony\Components\Form\Configurable;

/**
 * Implements functionality shared by most value transformers
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
abstract class BaseValueTransformer extends Configurable implements ValueTransformerInterface
{
    /**
     * The locale of this transformer as accepted by the class Locale
     * @var string
     */
    protected $locale;

    /**
     * Constructor.
     *
     * @param array $options     An array of options
     *
     * @throws \InvalidArgumentException when a option is not supported
     * @throws \RuntimeException         when a required option is not given
     */
    public function __construct(array $options = array())
    {
        $this->locale = class_exists('\Locale', false) ? \Locale::getDefault() : 'en';

        parent::__construct($options);
    }

    /**
     * {@inheritDoc}
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }
}