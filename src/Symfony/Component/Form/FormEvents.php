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
    /**
     * @Event
     */
    const PRE_SUBMIT = 'form.pre_bind';

    /**
     * @Event
     */
    const SUBMIT = 'form.bind';

    /**
     * @Event
     */
    const POST_SUBMIT = 'form.post_bind';

    /**
     * @Event
     */
    const PRE_SET_DATA = 'form.pre_set_data';

    /**
     * @Event
     */
    const POST_SET_DATA = 'form.post_set_data';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link PRE_SUBMIT} instead.
     *
     * @Event
     */
    const PRE_BIND = 'form.pre_bind';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link SUBMIT} instead.
     *
     * @Event
     */
    const BIND = 'form.bind';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link POST_SUBMIT} instead.
     *
     * @Event
     */
    const POST_BIND = 'form.post_bind';

    private function __construct()
    {
    }
}
