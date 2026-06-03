<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

/**
 * @author Christian Gripp <mail@core23.de>
 */
enum ExposeSecurityLevel: string
{
    case None = 'none';
    case AccountStatus = 'account_status';
    case All = 'all';
}
