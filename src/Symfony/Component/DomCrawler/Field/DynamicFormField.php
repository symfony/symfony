<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * DynamicFormField is ta form field only used when the element may be created
 * dynamically or the existence of the element may not be visible on the initial
 * rendering but possibly valid as defined by the form type, form model or a
 * database entity.
 *
 * @author Juti Noppornpitak <jnopporn@shiroyuki.com>
 */
class DynamicFormField extends FormField
{
    /**
     * Constructor.
     *
     * @param string  $node     The node associated with this field
     * @param boolean $disabled The flag to indicate whether or not the field is disabled
     */
    public function __construct($name, $disabled = false)
    {
        $this->name     = $name;
        $this->disabled = $disabled;
    }

    /**
     * Returns true if the field should be included in the submitted values.
     *
     * @return Boolean true if the field should be included in the submitted values, false otherwise
     */
    public function hasValue()
    {
        return empty($this->value);
    }

    /**
     * Check if the current field is disabled
     *
     * @return Boolean
     */
    public function isDisabled()
    {
        return $this->disabled;
    }

    /**
     * Initializes the form field.
     */
    protected function initialize()
    {
        // Do nothing.
    }
}
