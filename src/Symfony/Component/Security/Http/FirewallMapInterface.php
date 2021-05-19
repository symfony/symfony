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

use Symfony\Component\HttpFoundation\Request;

/**
 * This interface must be implemented by firewall maps.
 */
interface FirewallMapInterface
{
    /**
     * Returns the authentication listeners, and the exception listener to use
     * for the given request.
     *
     * If there are no authentication listeners, the first inner array must be
     * empty.
     *
     * If there is no exception listener, the second element of the outer array
     * must be null.
     *
     * If there is no logout listener, the third element of the outer array
     * must be null.
     *
     * @return array of the format [[AuthenticationListener], ExceptionListener, LogoutListener]
     */
    public function getListeners(Request $request);
}
