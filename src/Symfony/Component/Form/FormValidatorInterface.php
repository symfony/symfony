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
 * This interface is deprecated. You should use a FormEvents::POST_BIND event
 * listener instead.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3.
 */
interface FormValidatorInterface
{
    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     */
    public function validate(FormInterface $form);
}
