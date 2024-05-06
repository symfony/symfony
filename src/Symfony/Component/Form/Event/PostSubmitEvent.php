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

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\FormEvent;

/**
 * This event is dispatched after the Form::submit()
 * once the model and view data have been denormalized.
 *
 * It can be used to fetch data after denormalization.
 */
final class PostSubmitEvent extends FormEvent
{
    public function setData(mixed $data): never
    {
        throw new BadMethodCallException('Form data cannot be changed during "form.post_submit", you should use "form.pre_submit" or "form.submit" instead.');
    }
}
