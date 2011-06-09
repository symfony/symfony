<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http;

final class SecurityEvents
{
    const INTERACTIVE_LOGIN = 'security.interactive_login';

    const SWITCH_USER = 'security.switch_user';
}
