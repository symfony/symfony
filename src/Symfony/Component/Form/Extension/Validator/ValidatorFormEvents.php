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

use Symfony\Component\Form\Extension\Validator\Event\PostValidateEvent;
use Symfony\Component\Form\Extension\Validator\Event\PreValidateEvent;

final class ValidatorFormEvents
{
    /**
     * This event is dispatched after validation completes.
     *
     * @Event("Symfony\Component\Form\Extension\Validator\Event\PreValidateEvent")
     */
    public const PRE_VALIDATE = 'form.pre_validate';

    /**
     * This event is dispatched after validation completes.
     *
     * @Event("Symfony\Component\Form\Extension\Validator\Event\PostValidateEvent")
     */
    public const POST_VALIDATE = 'form.post_validate';

    /**
     * Event aliases.
     *
     * These aliases can be consumed by RegisterListenersPass.
     */
    public const ALIASES = [
        PreValidateEvent::class => self::PRE_VALIDATE,
        PostValidateEvent::class => self::POST_VALIDATE,
    ];

    private function __construct()
    {
    }
}
