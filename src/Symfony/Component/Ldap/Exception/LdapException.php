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
 * LdapException is thrown if an LDAP operation fails. For BC compatibility
 * the classes ExtensionNotLoadedException, MalformedDistinguishedNameException,
 * and UnexpectedValueException extend this class, though they are not thrown
 * because of a failed LDAP operation.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class LdapException extends \RuntimeException implements ExceptionInterface
{
    /**
     * This constructor ensures that an error code is specified.
     *
     * @param int        $code     the LDAP error code
     * @param \Throwable $previous
     */
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        if (2 > \func_num_args()) {
            @trigger_error(sprintf('Not specifying the LDAP error code in "%s::__construct()" is deprecated since Symfony 4.4.', __CLASS__), E_USER_DEPRECATED);
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param string $messageFormat The exception message. Possible placeholders
     *                              are {errorCode} and {errorMsg} that will be
     *                              replaced by the LDAP error code and the LDAP
     *                              error message, respectively.
     * @param int    $errorCode     the LDAP error code
     *
     * @return static
     */
    public static function create(string $messageFormat, int $errorCode)
    {
        $message = strtr($messageFormat, [
            '{errorCode}' => $errorCode,
            '{errorMsg}' => ldap_err2str($errorCode),
        ]);

        return new static($message, $errorCode);
    }
}
