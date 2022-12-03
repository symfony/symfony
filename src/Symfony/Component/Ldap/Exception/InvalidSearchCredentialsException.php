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
 * InvalidSearchCredentialsException is thrown if binding to ldap fails when
 * using the configured search_dn and search_password.
 *
 * @author Jeroen de Boer <info@jayfrown.nl>
 */
class InvalidSearchCredentialsException extends InvalidCredentialsException
{
}
