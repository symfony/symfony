<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

final class Events
{
    const onCoreException = 'onCoreException';

    const onCoreRequest = 'onCoreRequest';

    const filterCoreController = 'filterCoreController';

    const onCoreView = 'onCoreView';

    const filterCoreRespone = 'filterCoreResponse';

    const onCoreSecurity = 'onCoreSecurity';
}