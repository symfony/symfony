<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Exception;

/**
 * InvalidCredentialsException is thrown if binding to ldap has been done with invalid credentials.
 *
 * @author Hamza Amrouche <hamza.simperfit@gmail.com>
 */
class InvalidCredentialsException extends ConnectionException
{
}
