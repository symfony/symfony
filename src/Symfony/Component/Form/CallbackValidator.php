<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Deprecated. You should use FormEvents::POST_BIND event listeners instead.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3.
 */
class CallbackValidator implements FormValidatorInterface
{
    private $callback;

    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function __construct($callback)
    {
        $this->callback = $callback;
    }

    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function validate(FormInterface $form)
    {
        return call_user_func($this->callback, $form);
    }
}
