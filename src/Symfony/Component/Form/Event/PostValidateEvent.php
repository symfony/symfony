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
 * This event is dispatched after the validation of the whole form tree.
 *
 * It can be used to react to the validity of the form.
 */
final class PostValidateEvent extends FormEvent
{
    public function setData(mixed $data): never
    {
        throw new BadMethodCallException('Form data cannot be changed during "form.post_validate", you should use "form.pre_submit" or "form.submit" instead.');
    }
}
