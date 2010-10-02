<?php

namespace Symfony\Component\Form\ValueTransformer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\Configurable;

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