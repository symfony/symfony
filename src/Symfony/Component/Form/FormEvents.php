<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class FormEvents
{
    const PRE_BIND = 'form.pre_bind';

    const BIND = 'form.bind';

    const POST_BIND = 'form.post_bind';

    const PRE_SET_DATA = 'form.pre_set_data';

    const POST_SET_DATA = 'form.post_set_data';

    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link PRE_BIND} instead.
     */
    const BIND_CLIENT_DATA = 'form.bind_client_data';

    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link BIND} instead.
     */
    const BIND_NORM_DATA = 'form.bind_norm_data';

    /**
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link PRE_SET_DATA} instead.
     */
    const SET_DATA = 'form.set_data';

    private function __construct()
    {
    }
}
