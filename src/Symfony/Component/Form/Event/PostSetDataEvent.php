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
 * This event is dispatched at the end of the Form::setData() method.
 *
 * It can be used to modify a form depending on the populated data (adding or
 * removing fields dynamically).
 */
final class PostSetDataEvent extends FormEvent
{
    /**
     * @deprecated since Symfony 6.4, it will throw an exception in 7.0.
     */
    public function setData(mixed $data): void
    {
        trigger_deprecation('symfony/form', '6.4', 'Calling "%s()" will throw an exception as of 7.0, listen to "form.pre_set_data" instead.', __METHOD__);
        // throw new BadMethodCallException('Form data cannot be changed during "form.post_set_data", you should use "form.pre_set_data" instead.');
        parent::setData($data);
    }
}
