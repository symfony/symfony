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
 * This class should extend \UnexpectedValueException, but extends LdapException
 * for BC compatibility.
 *
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class UnexpectedValueException extends LdapException implements ExceptionInterface
{
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        // avoid deprecation error
        parent::__construct($message, $code, $previous);
    }
}
