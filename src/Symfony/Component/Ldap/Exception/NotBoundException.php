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
 * NotBoundException is thrown if the connection with the LDAP server is not yet bound.
 *
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
class NotBoundException extends \RuntimeException implements ExceptionInterface
{
}
