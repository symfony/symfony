<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Exception;

use Symfony\Component\Form\FormTypeInterface;

class CircularReferenceException extends FormException
{
    public function __construct(FormTypeInterface $type, $code = 0, $previous = null)
    {
        parent::__construct(sprintf('Circular reference detected in the "%s" type (defined in class "%s").', $type->getName(), get_class($type)), $code, $previous);
    }
}
