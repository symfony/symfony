<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Deprecated;

trigger_error('Constants PRE_BIND, BIND and POST_BIND in class Symfony\Component\Form\FormEvents are deprecated since version 2.3 and will be removed in 3.0. Use PRE_SUBMIT, SUBMIT and POST_SUBMIT instead.', E_USER_DEPRECATED);

/**
 * @deprecated since version 2.7, to be removed in 3.0.
 * @internal
 */
final class FormEvents
{
    const PRE_BIND = 'form.pre_bind';
    const BIND = 'form.bind';
    const POST_BIND = 'form.post_bind';

    private function __construct()
    {
    }
}
