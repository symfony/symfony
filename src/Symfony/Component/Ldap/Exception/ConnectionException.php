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
 * ConnectionException is thrown if binding to ldap can not be established.
 *

 */
class ConnectionException extends \RuntimeException implements ExceptionInterface
{
}
