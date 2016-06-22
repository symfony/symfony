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
 * Submits forms if they were submitted.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface RequestHandlerInterface
{
    /**
     * Submits a form if it was submitted.
     *
     * @param FormInterface $form    The form to submit.
     * @param mixed         $request The current request.
     */
    public function handleRequest(FormInterface $form, $request = null);
}
