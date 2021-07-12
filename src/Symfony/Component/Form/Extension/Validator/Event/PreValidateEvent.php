<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Event;

use Symfony\Component\Form\FormEvent;

/**
 * This event is dispatched before validation begins.
 *
 * In this stage the model and view data may have been denormalized. Otherwise the form
 * is desynchronized because transformation failed during submission.
 *
 * It can be used to fetch data after denormalization.
 *
 * The event attaches the current view data. To know whether this is the renormalized data
 * or the invalid request data, call Form::isSynchronized() first.
 */
final class PreValidateEvent extends FormEvent
{
}
