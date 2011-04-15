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
final class Events
{
    const preBind = 'preBind';

    const postBind = 'postBind';

    const preSetData = 'preSetData';

    const postSetData = 'postSetData';

    const onBindClientData = 'onBindClientData';

    const onBindNormData = 'onBindNormData';

    const onSetData = 'onSetData';
}
