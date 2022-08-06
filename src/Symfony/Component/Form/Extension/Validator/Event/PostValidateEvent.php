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
 * This event is dispatched after the root form validation.
 */
final class PostValidateEvent extends FormEvent
{
}
