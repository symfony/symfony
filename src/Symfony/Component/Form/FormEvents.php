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
    const PRE_SUBMIT = 'form.pre_bind';

    const SUBMIT = 'form.bind';

    const POST_SUBMIT = 'form.post_bind';

    const PRE_SET_DATA = 'form.pre_set_data';

    const POST_SET_DATA = 'form.post_set_data';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link PRE_SUBMIT} instead.
     */
    const PRE_BIND = 'form.pre_bind';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link SUBMIT} instead.
     */
    const BIND = 'form.bind';

    /**
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link POST_SUBMIT} instead.
     */
    const POST_BIND = 'form.post_bind';

    private function __construct()
    {
    }
}
