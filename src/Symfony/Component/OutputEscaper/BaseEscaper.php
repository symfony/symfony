<?php

namespace Symfony\Component\OutputEscaper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract class that provides an interface for output escaping.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Mike Squire <mike@somosis.co.uk>
 */
abstract class BaseEscaper
{
    /**
     * The value that is to be escaped.
     */
    protected $value;

    /**
     * The escaper (a PHP callable or a named escaper) that is going to be applied to the value and its children.
     */
    protected $escaper;

    /**
     * Constructor.
     *
     * Since BaseEscaper is an abstract class, instances cannot be created
     * directly but the constructor will be inherited by sub-classes.
     *
     * @param mixed  $escaper The escaping method (a PHP callable or a named escaper)
     * @param string $value   Escaping value
     */
    public function __construct($escaper, $value)
    {
        $this->escaper = $escaper;
        $this->value = $value;
    }

    /**
     * Sets the default escaper to use.
     *
     * @param mixed  $escaper The escaping method (a PHP callable or a named escaper)
     */
    public function setEscaper($escaper)
    {
        $this->escaper = $escaper;
    }

    /**
     * Returns the raw value associated with this instance.
     *
     * Concrete instances of BaseEscaper classes decorate a value which is
     * stored by the constructor. This returns that original, unescaped, value.
     *
     * @return mixed The original value used to construct the decorator
     */
    public function getRawValue()
    {
        return $this->value;
    }
}
