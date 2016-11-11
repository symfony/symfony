<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Exception;

/**
 * An InvalidMarkingException is thrown when a Marking does not
 * match the current workflow.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class InvalidMarkingException extends LogicException
{
    /**
     * @param string $expectedClass
     * @param mixed  $marking
     */
    public function __construct($expectedClass, $marking)
    {
        $this->message = sprintf('Marking must be an instance of "%", but got "%"', $expectedClass, is_object($marking) ? get_class($marking) : gettype($marking));
    }
}
