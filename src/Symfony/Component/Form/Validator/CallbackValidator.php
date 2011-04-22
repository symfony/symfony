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

use Symfony\Component\Form\FormInterface;

class CallbackValidator implements FormValidatorInterface
{
    private $callback;

    public function __construct($callback)
    {
        // TODO validate callback

        $this->callback = $callback;
    }

    public function validate(FormInterface $form)
    {
        return call_user_func($this->callback, $form);
    }
}