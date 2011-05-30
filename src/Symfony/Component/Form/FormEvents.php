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
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
final class FormEvents
{
    const PRE_BIND = 'form.pre_bind';

    const POST_BIND = 'form.post_bind';

    const PRE_SET_DATA = 'form.pre_set_data';

    const POST_SET_DATA = 'form.post_set_data';

    const ON_BIND_CLIENT_DATA = 'form.on_bind_client_data';

    const ON_BIND_NORM_DATA = 'form.on_bind_norm_data';

    const ON_SET_DATA = 'form.on_set_data';
}
