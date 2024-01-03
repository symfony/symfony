<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Event;

use Symfony\Component\Form\FormEvent;

/**
 * This event is dispatched after the Form::submit()
 * once the model and view data have been denormalized.
 *
 * It can be used to fetch data after denormalization.
 */
final class PostSubmitEvent extends FormEvent
{
    /**
     * @deprecated since Symfony 6.4, it will throw an exception in 7.0.
     */
    public function setData(mixed $data): void
    {
        trigger_deprecation('symfony/form', '6.4', 'Calling "%s()" will throw an exception as of 7.0, listen to "form.pre_submit" or "form.submit" instead.', __METHOD__);
        // throw new BadMethodCallException('Form data cannot be changed during "form.post_submit", you should use "form.pre_submit" or "form.submit" instead.');
        parent::setData($data);
    }
}
