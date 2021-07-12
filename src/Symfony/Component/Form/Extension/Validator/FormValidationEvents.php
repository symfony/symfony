<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator;

final class FormValidationEvents
{
    /**
     * @see Event\PreValidateEvent
     * @Event(Event\PreValidateEvent::class)
     */
    const PRE_VALIDATE = 'form.pre_validate';

    /**
     * This event is dispatched after validation completes.
     *
     * In this stage, the form will return a correct value to Form::isValid() and allow for
     * further working with the form data.
     *
     * @see Event\PostValidateEvent
     * @Event(Event\PostValidateEvent::class)
     */
    const POST_VALIDATE = 'form.post_validate';
}
