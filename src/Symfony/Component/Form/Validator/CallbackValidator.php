<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Validator;

use Symfony\Component\Form\FieldInterface;

class CallbackValidator implements FieldValidatorInterface
{
    private $callback;

    public function __construct($callback)
    {
        // TODO validate callback

        $this->callback = $callback;
    }

    public function validate(FieldInterface $field)
    {
        return call_user_func($this->callback, $field);
    }
}