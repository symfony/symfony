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
 * LdapException is thrown if an LDAP operation fails.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class LdapException extends \RuntimeException implements ExceptionInterface
{
    /**
     * This constructor ensures that an error code is specified.
     *
     * @param string     $message
     * @param int        $code     The LDAP error code.
     * @param \Throwable $previous
     */
    public function __construct(string $message, int $code, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param string $messageFormat The exception message. Possible placeholders
     *                              are {errorCode} and {errorMsg} that will be
     *                              replaced by the LDAP error code and the LDAP
     *                              error message, respectively.
     * @param int    $errorCode     The LDAP error code.
     *
     * @return static
     */
    public static function create(string $messageFormat, int $errorCode)
    {
        $message = strtr($messageFormat, [
            '{errorCode}' => $errorCode,
            '{errorMsg}'  => ldap_err2str($errorCode),
        ]);

        return new static($message, $errorCode);
    }
}
