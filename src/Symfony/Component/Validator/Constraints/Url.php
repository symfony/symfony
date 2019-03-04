<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Url extends Constraint
{
    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_ANY = 'ANY';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_NONE = false;

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_A = 'A';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_A6 = 'A6';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_AAAA = 'AAAA';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_CNAME = 'CNAME';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_MX = 'MX';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_NAPTR = 'NAPTR';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_NS = 'NS';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_PTR = 'PTR';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_SOA = 'SOA';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_SRV = 'SRV';

    /**
     * @deprecated since Symfony 4.1
     */
    const CHECK_DNS_TYPE_TXT = 'TXT';

    const INVALID_URL_ERROR = '57c2f299-1154-4870-89bb-ef3b1f5ad229';

    protected static $errorNames = [
        self::INVALID_URL_ERROR => 'INVALID_URL_ERROR',
    ];

    public $message = 'This value is not a valid URL.';

    /**
     * @deprecated since Symfony 4.1
     */
    public $dnsMessage = 'The host could not be resolved.';
    public $protocols = ['http', 'https'];

    /**
     * @deprecated since Symfony 4.1
     */
    public $checkDNS = self::CHECK_DNS_TYPE_NONE;
    public $relativeProtocol = false;

    public function __construct($options = null)
    {
        if (\is_array($options)) {
            if (\array_key_exists('checkDNS', $options)) {
                @trigger_error(sprintf('The "checkDNS" option in "%s" is deprecated since Symfony 4.1. Its false-positive rate is too high to be relied upon.', self::class), E_USER_DEPRECATED);
            }
            if (\array_key_exists('dnsMessage', $options)) {
                @trigger_error(sprintf('The "dnsMessage" option in "%s" is deprecated since Symfony 4.1.', self::class), E_USER_DEPRECATED);
            }
        }

        parent::__construct($options);
    }
}
