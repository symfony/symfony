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
 * This event is dispatched at the beginning of the Form::submit() method.
 *
 * It can be used to:
 *  - Change data from the request, before submitting the data to the form.
 *  - Add or remove form fields, before submitting the data to the form.
 *
 * @final since Symfony 4.4
 */
class PreSubmitEvent extends FormEvent
{
}
