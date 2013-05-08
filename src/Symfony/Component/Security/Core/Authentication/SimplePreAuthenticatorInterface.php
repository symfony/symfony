<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @experimental This feature is experimental in 2.3 and might change in future versions
 */
interface SimplePreAuthenticatorInterface extends SimpleAuthenticatorInterface
{
    public function createToken(Request $request, $providerKey);
}
