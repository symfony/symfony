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
 * Binds forms from requests if they were submitted.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormProcessorInterface
{
    /**
     * Binds a form from a request if it was submitted.
     *
     * @param FormInterface $form    The form to bind.
     * @param mixed         $request The current request.
     */
    public function processForm(FormInterface $form, $request = null);
}
