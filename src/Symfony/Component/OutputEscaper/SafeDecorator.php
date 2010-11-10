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
 * Marks a variable as being safe for output.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SafeDecorator implements SafeDecoratorInterface
{
    protected $value;

    /**
     * Constructor.
     *
     * @param mixed $value  The value to mark as safe
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the raw value.
     *
     * @return mixed The raw value
     */
    public function getRawValue()
    {
        return $this->value;
    }
}
